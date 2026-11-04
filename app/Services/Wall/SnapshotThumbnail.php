<?php

namespace App\Services\Wall;

use Illuminate\Support\Facades\File;

class SnapshotThumbnail
{
    public const WIDTH = 640;

    /**
     * Returns the public-relative path of the resized preview (created on demand),
     * or null when the original cannot be resized.
     */
    public static function ensure(string $imagePath, ?string $basePath = null): ?string
    {
        $basePath = rtrim($basePath ?? public_path(), '\\/');
        $source = $basePath.'/'.$imagePath;
        $thumbPath = 'images/snapshots/thumbs/'.pathinfo($imagePath, PATHINFO_FILENAME).'.png';
        $target = $basePath.'/'.$thumbPath;

        if (is_file($target)) {
            return $thumbPath;
        }

        if (! is_file($source) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $original = @imagecreatefrompng($source);
        if ($original === false) {
            return null;
        }

        $width = imagesx($original);
        $height = imagesy($original);

        if ($width <= self::WIDTH) {
            imagedestroy($original);

            return null;
        }

        $thumbHeight = max(1, (int) round($height * self::WIDTH / $width));
        $thumb = imagecreatetruecolor(self::WIDTH, $thumbHeight);
        imagecopyresampled($thumb, $original, 0, 0, 0, 0, self::WIDTH, $thumbHeight, $width, $height);

        File::ensureDirectoryExists(dirname($target), 0755, true);
        imagepng($thumb, $target, 9);
        imagedestroy($thumb);
        imagedestroy($original);

        return $thumbPath;
    }
}
