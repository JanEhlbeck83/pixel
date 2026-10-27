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
    protected $signature = 'snapshot:generate';

    protected $description = 'Create a full-size gallery snapshot of the whole wall (composed from the up-to-date tiles)';

    public function handle(): int
    {
        $this->call('wall:tiles');

        $dimensions = DB::table('wall_dimensions')->where('id', 1)->first();
        $wallWidth = (int) $dimensions->width;
        $wallHeight = (int) $dimensions->height;
        $outputPath = rtrim((string) config('wall.output_path', public_path()), '\\/');
        $tileDirectory = $outputPath.'/images/wall-tiles';
        $snapshotDirectory = $outputPath.'/images/snapshots';
        File::ensureDirectoryExists($snapshotDirectory, 0755, true);

        $timestamp = now();
        $filename = 'snapshot-'.$timestamp->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.png';
        $snapshotPath = $snapshotDirectory.'/'.$filename;

        $image = imagecreatetruecolor($wallWidth, $wallHeight);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        $size = GenerateWallTilesCommand::TILE_SIZE;
        for ($y = 0; $y < $wallHeight; $y += $size) {
            for ($x = 0; $x < $wallWidth; $x += $size) {
                $tile = @imagecreatefrompng($tileDirectory.'/'.$x.'-'.$y.'.png');
                if ($tile === false) {
                    continue;
                }
                imagecopy($image, $tile, $x, $y, 0, 0, imagesx($tile), imagesy($tile));
                imagedestroy($tile);
            }
        }

        imagepng($image, $snapshotPath);
        imagedestroy($image);
        Snapshot::create([
            'label' => 'Snapshot '.$timestamp->format('d.m.Y H:i'),
            'summary' => [
                'painted_pixels' => Pixel::count(),
                'width' => $wallWidth,
                'height' => $wallHeight,
                'tile_size' => $size,
                'generated_at' => $timestamp->toISOString(),
            ],
            'image_path' => 'images/snapshots/'.$filename,
        ]);

        $this->info('Snapshot generated: '.$snapshotPath);

        return self::SUCCESS;
    }
}
