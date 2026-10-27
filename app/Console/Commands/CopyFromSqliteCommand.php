<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CopyFromSqliteCommand extends Command
{
    protected $signature = 'db:copy-from-sqlite
        {--chunk=5000 : Zeilen pro Schreibvorgang}
        {--force : Ziel-Tabellen auch leeren, wenn sie schon Daten enthalten}';

    protected $description = 'Kopiert alle Daten aus der alten SQLite-Datei (Verbindung sqlite_legacy) in die aktuelle Standard-Datenbank (z. B. PostgreSQL).';

    /** Reihenfolge beachtet die Fremdschlüssel. */
    private const TABLES = [
        'users', 'password_reset_tokens', 'wall_dimensions', 'pixels', 'pixel_edit_locks',
        'activities', 'snapshots', 'feedback', 'orders',
    ];

    public function handle(): int
    {
        $target = DB::connection();
        if ($target->getDriverName() === 'sqlite') {
            $this->error('DB_CONNECTION steht noch auf sqlite. Erst .env auf pgsql umstellen und `php artisan migrate` ausführen.');

            return self::FAILURE;
        }

        $source = DB::connection('sqlite_legacy');
        $chunk = max(100, (int) $this->option('chunk'));

        foreach (self::TABLES as $table) {
            if (! Schema::connection('sqlite_legacy')->hasTable($table) || ! Schema::hasTable($table)) {
                $this->warn("$table: übersprungen (Tabelle fehlt)");

                continue;
            }

            if ($target->table($table)->exists()) {
                if (! $this->option('force')) {
                    $this->error("$table enthält schon Daten. Mit --force leeren und neu kopieren.");

                    return self::FAILURE;
                }
                $target->statement('TRUNCATE TABLE "'.$table.'" RESTART IDENTITY CASCADE');
            }
        }

        foreach (self::TABLES as $table) {
            if (! Schema::connection('sqlite_legacy')->hasTable($table) || ! Schema::hasTable($table)) {
                continue;
            }

            $total = $source->table($table)->count();
            $bar = $this->output->createProgressBar($total);
            $bar->setFormat(" $table: %current%/%max% [%bar%] %percent:3s%%");
            $hasId = Schema::hasColumn($table, 'id');
            $columns = Schema::getColumnListing($table);

            $copy = function ($rows) use ($target, $table, $columns, $bar) {
                $batch = $rows->map(fn ($row) => array_intersect_key((array) $row, array_flip($columns)))->all();
                if ($batch !== []) {
                    $target->table($table)->insert($batch);
                    $bar->advance(count($batch));
                }
            };

            if ($hasId) {
                $source->table($table)->orderBy('id')->chunkById($chunk, $copy);
            } else {
                $source->table($table)->orderBy($columns[0])->chunk($chunk, $copy);
            }
            $bar->finish();
            $this->newLine();

            if ($hasId) {
                $target->statement(
                    "SELECT setval(pg_get_serial_sequence('\"$table\"', 'id'), COALESCE((SELECT MAX(id) FROM \"$table\"), 1), (SELECT MAX(id) IS NOT NULL FROM \"$table\"))"
                );
            }

            $targetCount = $target->table($table)->count();
            if ($targetCount !== $total) {
                $this->error("$table: Quelle $total, Ziel $targetCount – Abbruch.");

                return self::FAILURE;
            }
        }

        $this->info('Fertig. Alle Tabellen stimmen in der Zeilenanzahl überein.');

        return self::SUCCESS;
    }
}
