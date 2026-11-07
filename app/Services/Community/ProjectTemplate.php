<?php

namespace App\Services\Community;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectTemplate
{
    public function validateCrop(string $path, int $width, int $height, int $x, int $y): void
    {
        $size = getimagesize($path);
        if (! $size || ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
            || $size[0] > 4096 || $size[1] > 4096) {
            throw ValidationException::withMessages(['image' => __('Bitte lade PNG, JPG oder WebP mit höchstens 4096 Pixeln je Seite hoch.')]);
        }
        if ($x < 0 || $y < 0 || $x + $width > $size[0] || $y + $height > $size[1]) {
            throw ValidationException::withMessages(['image' => __('Das Gesamtbild muss mindestens :width × :height Pixel groß sein. Der Ausschnitt muss vollständig im Bild liegen.', compact('width', 'height'))]);
        }
    }

    public function image(string $path, int $width, int $height, int $x, int $y, bool $original)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, __('Das Vorlagenbild wurde nicht gefunden.'));
        if ($original) {
            return response()->file($disk->path($path), ['Cache-Control' => 'private, no-store'])->setPrivate();
        }
        $this->validateCrop($disk->path($path), $width, $height, $x, $y);
        $source = imagecreatefromstring($disk->get($path));
        if ($source === false) {
            throw new RuntimeException('Unable to decode project template.');
        }
        $crop = imagecrop($source, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        imagedestroy($source);
        if ($crop === false) {
            throw new RuntimeException('Unable to crop project template.');
        }
        imagesavealpha($crop, true);
        ob_start();
        $saved = imagepng($crop);
        $bytes = ob_get_clean();
        imagedestroy($crop);
        if (! $saved) {
            throw new RuntimeException('Unable to encode project template.');
        }

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }
}
