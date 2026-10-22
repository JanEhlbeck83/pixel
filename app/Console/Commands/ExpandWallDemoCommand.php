<?php

namespace App\Console\Commands;

use App\Models\Pixel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpandWallDemoCommand extends Command
{
    private const INITIAL_SIZE = 1024;

    private const EXTRA_SIZE = 2400;

    private const BATCH_SIZE = 2000;

    protected $signature = 'wall:expand-demo';

    protected $description = 'Extend the Trump portrait wall by 2400 px in each direction and paint pixel art';

    private int $targetWidth;

    private int $targetHeight;

    public function handle(): int
    {
        $dimensions = DB::table('wall_dimensions')->where('id', 1)->first();
        if (! $dimensions) {
            $this->error('Wall dimensions are missing. Run the database migrations first.');

            return self::FAILURE;
        }

        $this->targetWidth = self::INITIAL_SIZE + self::EXTRA_SIZE;
        $this->targetHeight = self::INITIAL_SIZE + self::EXTRA_SIZE;

        if ((int) $dimensions->width === self::INITIAL_SIZE && (int) $dimensions->height === self::INITIAL_SIZE) {
            $this->createWhiteExtension();
        } elseif ((int) $dimensions->width !== $this->targetWidth || (int) $dimensions->height !== $this->targetHeight) {
            $this->error('The wall must be 1024 × 1024 px or already be expanded to 3424 × 3424 px.');

            return self::FAILURE;
        }

        $this->paintPixelArt();
        $this->generateTiles();

        $this->info('Wall expanded to 3424 × 3424 px with pixel art to the right and below the portrait.');

        return self::SUCCESS;
    }

    private function createWhiteExtension(): void
    {
        $timestamp = now();
        $bar = $this->output->createProgressBar(self::INITIAL_SIZE + self::EXTRA_SIZE);
        $bar->start();

        DB::transaction(function () use ($timestamp, $bar) {
            $wall = DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->first();
            if ((int) $wall->width !== self::INITIAL_SIZE || (int) $wall->height !== self::INITIAL_SIZE) {
                throw new \RuntimeException('Wall dimensions changed while preparing the extension.');
            }

            for ($y = 0; $y < self::INITIAL_SIZE; $y++) {
                $this->insertWhiteRows(self::INITIAL_SIZE, $this->targetWidth, $y, $y, $timestamp);
                $bar->advance();
            }

            for ($y = self::INITIAL_SIZE; $y < $this->targetHeight; $y++) {
                $this->insertWhiteRows(0, $this->targetWidth, $y, $y, $timestamp);
                $bar->advance();
            }

            DB::table('wall_dimensions')->where('id', 1)->update([
                'width' => $this->targetWidth,
                'height' => $this->targetHeight,
                'updated_at' => $timestamp,
            ]);
        });

        $bar->finish();
        $this->newLine();
        $this->info('White pixels added to the expanded area.');
    }

    private function insertWhiteRows(int $startX, int $endX, int $startY, int $endY, $timestamp): void
    {
        $rows = [];
        for ($y = $startY; $y <= $endY; $y++) {
            for ($x = $startX; $x < $endX; $x++) {
                $rows[] = [
                    'x' => $x,
                    'y' => $y,
                    'color' => '#ffffff',
                    'owner_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) === self::BATCH_SIZE) {
                    Pixel::query()->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            Pixel::query()->insert($rows);
        }
    }

    private function paintPixelArt(): void
    {
        $sky = '#91d8f7';
        $backMountain = '#55738f';
        $frontMountain = '#38566f';
        $grass = '#69ad59';
        $darkGrass = '#36734c';
        $water = '#2589b8';
        $white = '#ffffff';

        DB::transaction(function () use ($sky, $backMountain, $frontMountain, $grass, $darkGrass, $water, $white) {
            $this->paintRect(self::INITIAL_SIZE, 0, $this->targetWidth - 1, self::INITIAL_SIZE - 1, $sky);
            $this->drawCloud(1300, 170);
            $this->drawCloud(2210, 110);
            $this->drawPixelCircle(3010, 260, 132, '#ffbe45');
            $this->drawPixelCircle(3010, 260, 84, '#ffe17a');
            $this->drawMountain(1760, 300, 810, 24, 32, $backMountain);
            $this->drawMountain(2580, 350, 810, 24, 28, $frontMountain);
            $this->paintRect(self::INITIAL_SIZE, 770, $this->targetWidth - 1, 835, $water);
            $this->paintRect(self::INITIAL_SIZE, 836, $this->targetWidth - 1, 1023, $grass);
            $this->drawPine(1180, 838, 220, $darkGrass);
            $this->drawPine(1540, 856, 190, $darkGrass);
            $this->drawPine(2080, 840, 230, $darkGrass);
            $this->drawPine(2780, 850, 205, $darkGrass);
            $this->drawPine(3230, 844, 225, $darkGrass);

            $this->paintRect(0, self::INITIAL_SIZE, $this->targetWidth - 1, $this->targetHeight - 1, $sky);
            $this->drawCloud(260, 1200);
            $this->drawCloud(1370, 1170);
            $this->drawCloud(2500, 1210);
            $this->drawPixelCircle(2970, 1420, 156, '#ffbe45');
            $this->drawPixelCircle(2970, 1420, 96, '#ffe17a');
            $this->drawMountain(660, 1510, 2180, 24, 28, $backMountain);
            $this->drawMountain(1800, 1450, 2180, 24, 34, $frontMountain);
            $this->drawMountain(3010, 1530, 2180, 24, 30, $backMountain);
            $this->paintRect(0, 2181, $this->targetWidth - 1, 2690, $water);
            $this->paintRect(0, 2220, $this->targetWidth - 1, 2255, '#55c3db');
            $this->paintRect(0, 2370, $this->targetWidth - 1, 2400, '#55c3db');
            $this->paintRect(0, 2530, $this->targetWidth - 1, 2565, '#55c3db');
            $this->paintRect(0, 2691, $this->targetWidth - 1, $this->targetHeight - 1, $grass);

            foreach ([140, 470, 900, 1270, 1660, 2080, 2430, 2810, 3190] as $index => $x) {
                $this->drawPine($x, 2710 + (($index % 3) * 18), 270 + (($index % 2) * 30), $darkGrass);
            }

            $this->drawFlower(350, 3260, $white, '#ff7096');
            $this->drawFlower(1100, 3300, $white, '#ffd166');
            $this->drawFlower(1880, 3250, $white, '#b28dff');
            $this->drawFlower(2550, 3310, $white, '#ff7096');
        });
    }

    private function paintRect(int $left, int $top, int $right, int $bottom, string $color): void
    {
        $left = max(0, $left);
        $top = max(0, $top);
        $right = min($this->targetWidth - 1, $right);
        $bottom = min($this->targetHeight - 1, $bottom);

        if ($left > $right || $top > $bottom) {
            return;
        }

        DB::table('pixels')
            ->whereBetween('x', [$left, $right])
            ->whereBetween('y', [$top, $bottom])
            ->update([
                'color' => $color,
                'owner_user_id' => null,
                'updated_by_user_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function drawCloud(int $x, int $y): void
    {
        $this->paintRect($x + 24, $y, $x + 168, $y + 47, '#ffffff');
        $this->paintRect($x, $y + 24, $x + 216, $y + 71, '#ffffff');
        $this->paintRect($x + 48, $y + 72, $x + 168, $y + 95, '#ffffff');
    }

    private function drawPixelCircle(int $centerX, int $centerY, int $radius, string $color): void
    {
        $band = 24;
        for ($offset = -$radius; $offset <= $radius; $offset += $band) {
            $halfWidth = (int) (sqrt(max(0, ($radius * $radius) - ($offset * $offset))) / $band) * $band;
            $this->paintRect(
                $centerX - $halfWidth,
                $centerY + $offset,
                $centerX + $halfWidth + $band - 1,
                $centerY + $offset + $band - 1,
                $color
            );
        }
    }

    private function drawMountain(int $peakX, int $peakY, int $baseY, int $band, int $halfWidthPerBand, string $color): void
    {
        for ($top = $peakY; $top < $baseY; $top += $band) {
            $halfWidth = (int) (($top - $peakY) / $band) * $halfWidthPerBand;
            $this->paintRect(
                $peakX - $halfWidth,
                $top,
                $peakX + $halfWidth + $band - 1,
                min($baseY, $top + $band - 1),
                $color
            );
        }
    }

    private function drawPine(int $centerX, int $baseY, int $height, string $color): void
    {
        $block = 24;
        $trunkWidth = 48;
        $this->paintRect($centerX - intdiv($trunkWidth, 2), $baseY - 120, $centerX + intdiv($trunkWidth, 2), $baseY, '#795548');

        for ($top = $baseY - $height; $top < $baseY - 96; $top += $block) {
            $halfWidth = (int) (($top - ($baseY - $height)) / $block) * 24;
            $this->paintRect(
                $centerX - $halfWidth - $block,
                $top,
                $centerX + $halfWidth + $block,
                $top + $block - 1,
                $color
            );
        }
    }

    private function drawFlower(int $centerX, int $centerY, string $petalColor, string $centerColor): void
    {
        $this->paintRect($centerX - 24, $centerY, $centerX + 24, $centerY + 48, $petalColor);
        $this->paintRect($centerX, $centerY - 24, $centerX + 48, $centerY + 24, $petalColor);
        $this->paintRect($centerX, $centerY, $centerX + 24, $centerY + 24, $centerColor);
        $this->paintRect($centerX, $centerY + 48, $centerX + 24, $centerY + 144, '#36734c');
    }

    private function generateTiles(): void
    {
        $this->call('snapshot:generate');
    }
}
