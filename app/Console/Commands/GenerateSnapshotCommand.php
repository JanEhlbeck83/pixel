<?php

namespace App\Console\Commands;

use App\Models\Pixel;
use App\Models\Snapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateSnapshotCommand extends Command
{
    private const TILE_SIZE = 300;

    protected $signature = 'snapshot:generate';

    protected $description = 'Generate 300 px wall tiles and a viewport snapshot';

    public function handle(): int
    {
        $dimensions = DB::table('wall_dimensions')->where('id', 1)->first();
        $wallWidth = (int) $dimensions->width;
        $wallHeight = (int) $dimensions->height;
        $tileDirectory = public_path('images/wall-tiles');
        $snapshotDirectory = public_path('images/snapshots');
        File::ensureDirectoryExists($tileDirectory, 0755, true);
        File::ensureDirectoryExists($snapshotDirectory, 0755, true);

        $timestamp = now();
        $filename = 'snapshot-'.$timestamp->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.png';
        $snapshotPath = $snapshotDirectory.'/'.$filename;
        $viewportWidth = min(1600, $wallWidth);
        $viewportHeight = min(1600, $wallHeight);
        $viewport = imagecreatetruecolor($viewportWidth, $viewportHeight);
        $white = imagecolorallocate($viewport, 255, 255, 255);
        imagefill($viewport, 0, 0, $white);

        for ($y = 0; $y < $wallHeight; $y += self::TILE_SIZE) {
            for ($x = 0; $x < $wallWidth; $x += self::TILE_SIZE) {
                $tileWidth = min(self::TILE_SIZE, $wallWidth - $x);
                $tileHeight = min(self::TILE_SIZE, $wallHeight - $y);
                $tile = imagecreatetruecolor($tileWidth, $tileHeight);
                $tileWhite = imagecolorallocate($tile, 255, 255, 255);
                imagefill($tile, 0, 0, $tileWhite);

                Pixel::query()
                    ->whereBetween('x', [$x, $x + $tileWidth - 1])
                    ->whereBetween('y', [$y, $y + $tileHeight - 1])
                    ->cursor(['x', 'y', 'color'])
                    ->each(function ($pixel) use ($tile, $x, $y) {
                        $rgb = $this->hexToRgb($pixel->color ?? '#ffffff');
                        $color = imagecolorallocate($tile, $rgb['r'], $rgb['g'], $rgb['b']);
                        imagesetpixel($tile, (int) $pixel->x - $x, (int) $pixel->y - $y, $color);
                    });

                imagepng($tile, $tileDirectory.'/'.$x.'-'.$y.'.png');
                if ($x < $viewportWidth && $y < $viewportHeight) {
                    imagecopy(
                        $viewport,
                        $tile,
                        $x,
                        $y,
                        0,
                        0,
                        min($tileWidth, $viewportWidth - $x),
                        min($tileHeight, $viewportHeight - $y)
                    );
                }
                imagedestroy($tile);
            }
        }
        $this->removeStaleTiles($tileDirectory, $wallWidth, $wallHeight);

        imagepng($viewport, $snapshotPath);
        imagedestroy($viewport);
        Snapshot::create([
            'label' => 'Snapshot '.$timestamp->format('d.m.Y H:i'),
            'summary' => [
                'painted_pixels' => Pixel::count(),
                'width' => $wallWidth,
                'height' => $wallHeight,
                'tile_size' => self::TILE_SIZE,
                'generated_at' => $timestamp->toISOString(),
            ],
            'image_path' => 'images/snapshots/'.$filename,
        ]);

        $this->info('Wall tiles and viewport snapshot generated: '.$snapshotPath);

        return self::SUCCESS;
    }

    private function removeStaleTiles(string $tileDirectory, int $wallWidth, int $wallHeight): void
    {
        foreach (File::files($tileDirectory) as $file) {
            if (! preg_match('/^(\d+)-(\d+)\.png$/D', $file->getFilename(), $matches)) {
                continue;
            }

            $x = (int) $matches[1];
            $y = (int) $matches[2];
            if (
                $x % self::TILE_SIZE !== 0
                || $y % self::TILE_SIZE !== 0
                || $x >= $wallWidth
                || $y >= $wallHeight
            ) {
                File::delete($file->getPathname());
            }
        }
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
