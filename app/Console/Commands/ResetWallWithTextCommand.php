<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ResetWallWithTextCommand extends Command
{
    private const WIDTH = 3840;

    private const HEIGHT = 2160;

    private const BATCH_SIZE = 2000;

    private const BACKGROUND = [0, 158, 224];

    private const FONT = [
        'F' => ['11111', '10000', '10000', '11110', '10000', '10000', '10000'],
        'U' => ['10001', '10001', '10001', '10001', '10001', '10001', '01110'],
        'C' => ['01111', '10000', '10000', '10000', '10000', '10000', '01111'],
        'K' => ['10001', '10010', '10100', '11000', '10100', '10010', '10001'],
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
        '!' => ['00100', '00100', '00100', '00100', '00100', '00000', '00100'],
        ' ' => ['000', '000', '000', '000', '000', '000', '000'],
    ];

    protected $signature = 'wall:reset-text
                            {--text="FUCK AFD!" : Text pro Zeile}
                            {--lines=4 : Anzahl der Zeilen}
                            {--image= : Bilddatei, 4× als 2×2-Raster, Ränder schwarz (statt Text)}
                            {--force : Ohne Rückfrage}';

    protected $description = 'Setzt die Wand auf 3840 × 2160 mit blauem Hintergrund und wiederholtem Text zurück, löscht alte Kacheln/Snapshots und erzeugt alles neu';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('GD-Erweiterung fehlt.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Alle Pixel, Sperren, Aktivitäten, Snapshots und Kacheln werden gelöscht. Fortfahren?')) {
            return self::FAILURE;
        }

        $image = $this->option('image')
            ? $this->createImageArtwork((string) $this->option('image'))
            : $this->createArtwork(strtoupper(trim((string) $this->option('text'), '"')), max(1, (int) $this->option('lines')));
        $now = now();

        DB::transaction(function () use ($image, $now) {
            foreach (['pixels', 'pixel_edit_locks', 'activities', 'snapshots'] as $table) {
                DB::table($table)->delete();
            }
            DB::table('users')->update(['free_pixels' => (int) config('payments.limits.user_max_free_pixels'), 'free_pixels_at' => null]);
            DB::table('wall_dimensions')->where('id', 1)->update(['width' => self::WIDTH, 'height' => self::HEIGHT, 'updated_at' => $now]);

            $rows = [];
            for ($y = 0; $y < self::HEIGHT; $y++) {
                for ($x = 0; $x < self::WIDTH; $x++) {
                    $rows[] = [
                        'x' => $x,
                        'y' => $y,
                        'color' => sprintf('#%06x', imagecolorat($image, $x, $y) & 0xFFFFFF),
                        'owner_user_id' => null,
                        'updated_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if (count($rows) === self::BATCH_SIZE) {
                        DB::table('pixels')->insert($rows);
                        $rows = [];
                    }
                }
                if ($y % 200 === 0) {
                    $this->line("Zeile $y / ".self::HEIGHT);
                }
            }
            if ($rows !== []) {
                DB::table('pixels')->insert($rows);
            }
        });
        imagedestroy($image);

        foreach (['images/wall-tiles', 'images/snapshots'] as $dir) {
            foreach (File::files(public_path($dir)) as $file) {
                if ($file->getExtension() === 'png') {
                    File::delete($file->getPathname());
                }
            }
        }

        if ($this->call('snapshot:generate') !== self::SUCCESS) {
            $this->error('Kacheln/Snapshot fehlgeschlagen. `php artisan snapshot:generate` erneut ausführen.');

            return self::FAILURE;
        }

        $this->info('Fertig: '.self::WIDTH.' × '.self::HEIGHT.' Pixel.');

        return self::SUCCESS;
    }

    /** Ordnet das Bild als 2 × 2-Raster an; verbleibende Ränder werden schwarz. */
    private function createImageArtwork(string $path): \GdImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            throw new \RuntimeException("Bild nicht lesbar: $path");
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));

        $cellW = intdiv(self::WIDTH, 2);
        $cellH = intdiv(self::HEIGHT, 2);
        $scale = min($cellW / imagesx($source), $cellH / imagesy($source));
        $w = (int) round(imagesx($source) * $scale);
        $h = (int) round(imagesy($source) * $scale);

        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 2; $col++) {
                imagecopyresampled(
                    $canvas, $source,
                    $col * $cellW + intdiv($cellW - $w, 2), $row * $cellH + intdiv($cellH - $h, 2), 0, 0,
                    $w, $h, imagesx($source), imagesy($source)
                );
            }
        }
        imagedestroy($source);

        return $canvas;
    }

    private function createArtwork(string $text, int $lines): \GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($image, 0, 0, imagecolorallocate($image, ...self::BACKGROUND));
        $white = imagecolorallocate($image, 255, 255, 255);

        $scale = 70;
        $gap = 20;
        $lineHeight = 7 * $scale;
        $lineGap = intdiv(self::HEIGHT - $lines * $lineHeight, $lines + 1);

        $glyphs = str_split($text);
        $textWidth = $gap * (count($glyphs) - 1);
        foreach ($glyphs as $char) {
            $textWidth += strlen(self::FONT[$char][0]) * $scale;
        }
        $startX = intdiv(self::WIDTH - $textWidth, 2);

        for ($line = 0; $line < $lines; $line++) {
            $y0 = $lineGap + $line * ($lineHeight + $lineGap);
            $x0 = $startX;
            foreach ($glyphs as $char) {
                foreach (self::FONT[$char] as $row => $pattern) {
                    foreach (str_split($pattern) as $col => $bit) {
                        if ($bit === '1') {
                            $px = $x0 + $col * $scale;
                            $py = $y0 + $row * $scale;
                            imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $white);
                        }
                    }
                }
                $x0 += strlen(self::FONT[$char][0]) * $scale + $gap;
            }
        }

        return $image;
    }
}
