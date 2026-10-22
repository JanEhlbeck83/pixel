<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPixelChaosWallCommand extends Command
{
    private const WALL_WIDTH = 3840;

    private const WALL_HEIGHT = 2160;

    private const BATCH_SIZE = 2000;

    private const FONT = [
        'A' => ['01110', '10001', '10001', '11111', '10001', '10001', '10001'],
        'C' => ['01111', '10000', '10000', '10000', '10000', '10000', '01111'],
        'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
        'H' => ['10001', '10001', '10001', '11111', '10001', '10001', '10001'],
        'I' => ['11111', '00100', '00100', '00100', '00100', '00100', '11111'],
        'L' => ['10000', '10000', '10000', '10000', '10000', '10000', '11111'],
        'O' => ['01110', '10001', '10001', '10001', '10001', '10001', '01110'],
        'P' => ['11110', '10001', '10001', '11110', '10000', '10000', '10000'],
        'S' => ['01111', '10000', '10000', '01110', '00001', '00001', '11110'],
        'X' => ['10001', '10001', '01010', '00100', '01010', '10001', '10001'],
    ];

    protected $signature = 'wall:reset-pixelchaos
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Replace the current wall with a 3840 × 2160 PixelChaos logo artwork';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required to generate the PixelChaos artwork.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This will delete all current wall pixels and locks, reset its dimensions, and generate new tiles. Continue?'
        )) {
            $this->warn('Wall reset cancelled.');

            return self::FAILURE;
        }

        $artwork = $this->createArtwork();
        $timestamp = now();
        $colors = [
            'white' => imagecolorallocate($artwork, 255, 255, 255),
        ];
        $rows = [];
        $paintedPixels = 0;

        DB::transaction(function () use ($artwork, $timestamp, &$colors, &$rows, &$paintedPixels): void {
            $dimensions = DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->first();
            if (! $dimensions) {
                throw new \RuntimeException('Wall dimensions are missing. Run the database migrations first.');
            }

            DB::table('pixels')->delete();
            DB::table('pixel_edit_locks')->delete();
            DB::table('wall_dimensions')->where('id', 1)->update([
                'width' => self::WALL_WIDTH,
                'height' => self::WALL_HEIGHT,
                'updated_at' => $timestamp,
            ]);

            for ($y = 0; $y < self::WALL_HEIGHT; $y++) {
                for ($x = 0; $x < self::WALL_WIDTH; $x++) {
                    $rgb = imagecolorat($artwork, $x, $y) & 0xFFFFFF;
                    if ($rgb === $colors['white']) {
                        continue;
                    }

                    $rows[] = [
                        'x' => $x,
                        'y' => $y,
                        'color' => sprintf('#%06x', $rgb),
                        'owner_user_id' => null,
                        'updated_by_user_id' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                    $paintedPixels++;

                    if (count($rows) === self::BATCH_SIZE) {
                        DB::table('pixels')->insert($rows);
                        $rows = [];
                    }
                }
            }

            if ($rows !== []) {
                DB::table('pixels')->insert($rows);
            }
        });

        imagedestroy($artwork);

        if ($this->call('snapshot:generate') !== self::SUCCESS) {
            $this->error('Wall data was reset, but tile and snapshot generation failed. Run snapshot:generate to retry.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'PixelChaos wall reset to %d × %d with %d artwork pixels. Previous pixel edits and locks were removed; historical snapshots were kept.',
            self::WALL_WIDTH,
            self::WALL_HEIGHT,
            $paintedPixels
        ));

        return self::SUCCESS;
    }

    private function createArtwork(): \GdImage
    {
        $image = imagecreatetruecolor(self::WALL_WIDTH, self::WALL_HEIGHT);
        $palette = [
            'white' => imagecolorallocate($image, 255, 255, 255),
            'navy' => imagecolorallocate($image, 20, 27, 45),
            'cyan' => imagecolorallocate($image, 0, 184, 217),
            'magenta' => imagecolorallocate($image, 231, 65, 145),
            'yellow' => imagecolorallocate($image, 255, 190, 59),
        ];
        imagefill($image, 0, 0, $palette['white']);

        $scale = 72;
        $letterGap = 22;
        $lineGap = 58;
        $lineWidth = 5 * 5 * $scale + 4 * $letterGap;
        $lineHeight = 7 * $scale;
        $artworkHeight = 300 + 76 + ($lineHeight * 2) + $lineGap + 44;
        $top = intdiv(self::WALL_HEIGHT - $artworkHeight, 2);
        $centerX = intdiv(self::WALL_WIDTH, 2);

        $this->drawLogoMark($image, $centerX, $top + 150, $palette);

        $this->drawWord($image, 'PIXEL', intdiv(self::WALL_WIDTH - $lineWidth, 2), $top + 376, $scale, $letterGap, $palette['navy']);
        $this->drawWord($image, 'CHAOS', intdiv(self::WALL_WIDTH - $lineWidth, 2), $top + 376 + $lineHeight + $lineGap, $scale, $letterGap, $palette['navy']);

        $barY = $top + $artworkHeight - 24;
        $barWidth = 420;
        imagefilledrectangle($image, $centerX - $barWidth, $barY, $centerX - 1, $barY + 23, $palette['cyan']);
        imagefilledrectangle($image, $centerX, $barY, $centerX + $barWidth - 1, $barY + 23, $palette['magenta']);

        return $image;
    }

    private function drawLogoMark(\GdImage $image, int $centerX, int $centerY, array $palette): void
    {
        $pattern = [
            '00111000',
            '01111100',
            '11101110',
            '11000110',
            '11000110',
            '11101110',
            '01111100',
            '00111000',
        ];
        $cellSize = 36;
        $startX = $centerX - intdiv(strlen($pattern[0]) * $cellSize, 2);
        $startY = $centerY - intdiv(count($pattern) * $cellSize, 2);
        $colors = ['cyan', 'magenta', 'yellow'];

        foreach ($pattern as $row => $line) {
            for ($column = 0; $column < strlen($line); $column++) {
                if ($line[$column] !== '1') {
                    continue;
                }

                $color = $palette[$colors[($row + $column) % count($colors)]];
                $x = $startX + $column * $cellSize;
                $y = $startY + $row * $cellSize;
                imagefilledrectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $color);
            }
        }
    }

    private function drawWord(
        \GdImage $image,
        string $word,
        int $startX,
        int $startY,
        int $scale,
        int $letterGap,
        int $color
    ): void {
        foreach (str_split($word) as $letterIndex => $letter) {
            foreach (self::FONT[$letter] as $row => $pattern) {
                for ($column = 0; $column < strlen($pattern); $column++) {
                    if ($pattern[$column] !== '1') {
                        continue;
                    }

                    $x = $startX + $letterIndex * (5 * $scale + $letterGap) + $column * $scale;
                    $y = $startY + $row * $scale;
                    imagefilledrectangle($image, $x, $y, $x + $scale - 1, $y + $scale - 1, $color);
                }
            }
        }
    }
}
