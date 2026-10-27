<?php

namespace App\Console\Commands;

use App\Models\Pixel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateWallTilesCommand extends Command
{
    public const TILE_SIZE = 300;

    protected $signature = 'wall:tiles {--full : Alle Kacheln neu erzeugen}';

    protected $description = 'Regenerate the 300 px wall tiles (only changed tiles unless --full)';

    public function handle(): int
    {
        $lock = Cache::lock('wall-tiles-generation', 1800);
        if (! $lock->get()) {
            $this->warn('Kachel-Erzeugung läuft bereits.');

            return self::SUCCESS;
        }

        try {
            return $this->generate();
        } finally {
            $lock->release();
        }
    }

    private function generate(): int
    {
        $startedAt = now();
        $dimensions = DB::table('wall_dimensions')->where('id', 1)->first();
        $wallWidth = (int) $dimensions->width;
        $wallHeight = (int) $dimensions->height;
        $tileDirectory = rtrim((string) config('wall.output_path', public_path()), '\\/').'/images/wall-tiles';
        File::ensureDirectoryExists($tileDirectory, 0755, true);

        $stateFile = $tileDirectory.'/.last-run';
        $since = ! $this->option('full') && is_file($stateFile) ? trim((string) file_get_contents($stateFile)) : null;

        $dirty = [];
        if ($since !== null && $since !== '') {
            Pixel::query()
                ->where('updated_at', '>=', $since)
                ->cursor(['x', 'y'])
                ->each(function ($pixel) use (&$dirty) {
                    $tx = intdiv((int) $pixel->x, self::TILE_SIZE) * self::TILE_SIZE;
                    $ty = intdiv((int) $pixel->y, self::TILE_SIZE) * self::TILE_SIZE;
                    $dirty[$tx.'-'.$ty] = true;
                });
        }

        $written = 0;
        for ($y = 0; $y < $wallHeight; $y += self::TILE_SIZE) {
            for ($x = 0; $x < $wallWidth; $x += self::TILE_SIZE) {
                $tileWidth = min(self::TILE_SIZE, $wallWidth - $x);
                $tileHeight = min(self::TILE_SIZE, $wallHeight - $y);
                $path = $tileDirectory.'/'.$x.'-'.$y.'.png';

                $needsRender = $since === null || $since === '' || isset($dirty[$x.'-'.$y]) || ! $this->tileMatches($path, $tileWidth, $tileHeight);
                if (! $needsRender) {
                    continue;
                }

                $this->renderTile($path, $x, $y, $tileWidth, $tileHeight);
                $written++;
            }
        }

        $this->removeStaleTiles($tileDirectory, $wallWidth, $wallHeight);
        // Kleiner Sicherheitsabstand, damit zeitgleiche Änderungen beim nächsten Lauf nicht verloren gehen.
        file_put_contents($stateFile, $startedAt->copy()->subSeconds(5)->format('Y-m-d H:i:s'));

        $this->info("Kacheln aktualisiert: {$written}");

        return self::SUCCESS;
    }

    private function tileMatches(string $path, int $width, int $height): bool
    {
        $size = is_file($path) ? @getimagesize($path) : false;

        return $size !== false && $size[0] === $width && $size[1] === $height;
    }

    private function renderTile(string $path, int $x, int $y, int $width, int $height): void
    {
        $tile = imagecreatetruecolor($width, $height);
        imagefill($tile, 0, 0, imagecolorallocate($tile, 255, 255, 255));
        $palette = [];

        Pixel::query()
            ->whereBetween('x', [$x, $x + $width - 1])
            ->whereBetween('y', [$y, $y + $height - 1])
            ->cursor(['x', 'y', 'color'])
            ->each(function ($pixel) use ($tile, $x, $y, &$palette) {
                $hex = ltrim($pixel->color ?? '#ffffff', '#');
                $palette[$hex] ??= imagecolorallocate(
                    $tile,
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2))
                );
                imagesetpixel($tile, (int) $pixel->x - $x, (int) $pixel->y - $y, $palette[$hex]);
            });

        // Atomar ersetzen, damit Spieler nie eine halb geschriebene Kachel laden.
        $temp = $path.'.tmp';
        imagepng($tile, $temp);
        imagedestroy($tile);
        rename($temp, $path);
    }

    private function removeStaleTiles(string $tileDirectory, int $wallWidth, int $wallHeight): void
    {
        foreach (File::files($tileDirectory) as $file) {
            if (! preg_match('/^(\d+)-(\d+)\.png$/D', $file->getFilename(), $matches)) {
                continue;
            }

            $x = (int) $matches[1];
            $y = (int) $matches[2];
            if ($x % self::TILE_SIZE !== 0 || $y % self::TILE_SIZE !== 0 || $x >= $wallWidth || $y >= $wallHeight) {
                File::delete($file->getPathname());
            }
        }
    }
}
