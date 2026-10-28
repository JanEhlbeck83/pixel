<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Laedt die kostenlose DB-IP "IP to Country Lite"-Datenbank (CC BY 4.0, db-ip.com) fuer die lokale Sprach-Erkennung.';

    public function handle(): int
    {
        $target = config('locales.geoip_database');
        File::ensureDirectoryExists(dirname($target));

        foreach ([now(), now()->subMonth()] as $month) {
            $url = 'https://download.db-ip.com/free/dbip-country-lite-'.$month->format('Y-m').'.mmdb.gz';
            $this->line("Lade $url");

            $response = Http::timeout(120)->get($url);
            if (! $response->successful()) {
                continue;
            }

            $data = @gzdecode($response->body());
            if ($data === false || strlen($data) < 1_000_000) {
                $this->warn('Unerwartete Datei, uebersprungen.');

                continue;
            }

            File::put($target.'.tmp', $data);
            File::move($target.'.tmp', $target);
            $this->info('GeoIP-Datenbank aktualisiert: '.$target);

            return self::SUCCESS;
        }

        $this->error('Download fehlgeschlagen. Ohne Datenbank wird die Sprache per Accept-Language gewaehlt.');

        return self::FAILURE;
    }
}
