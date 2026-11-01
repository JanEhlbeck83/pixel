<?php

namespace App\Services\Wall;

use App\Models\Pixel;
use App\Models\Report;
use Illuminate\Support\Facades\File;

class ReportSnapshot
{
    /** Hält den gemeldeten Zustand als PNG fest, falls der Bereich später übermalt wird. */
    public static function create(Report $report): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $image = imagecreatetruecolor($report->width, $report->height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        Pixel::query()
            ->whereBetween('x', [$report->x, $report->x + $report->width - 1])
            ->whereBetween('y', [$report->y, $report->y + $report->height - 1])
            ->get(['x', 'y', 'color'])
            ->each(function ($pixel) use ($image, $report) {
                $rgb = hexdec(ltrim($pixel->color, '#'));
                imagesetpixel($image, $pixel->x - $report->x, $pixel->y - $report->y, $rgb);
            });

        $path = $report->snapshotPath();
        File::ensureDirectoryExists(dirname($path), 0755, true);
        imagepng($image, $path);
        imagedestroy($image);
    }
}
