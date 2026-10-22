<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SimulatePixelChangesCommand extends Command
{
    protected $signature = 'pixel:simulate {count=100} {--x=900} {--y=900} {--random}';

    protected $description = 'Simulate a reserved pixel-editing session using complementary colors';

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($this->option('random')) {
            return $this->simulateRandomArea($count);
        }

        $side = (int) sqrt($count);
        $startX = (int) $this->option('x');
        $startY = (int) $this->option('y');

        if ($count < 1 || $side * $side !== $count) {
            $this->error('The pixel count must be a positive square number.');

            return self::FAILURE;
        }

        if ($startX < 0 || $startY < 0 || $startX + $side > 1024 || $startY + $side > 1024) {
            $this->error('The selected area is outside the 1024x1024 wall.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', 'admin@example.com')->first();
        if (! $user) {
            $this->error('The admin@example.com user does not exist.');

            return self::FAILURE;
        }

        try {
            $changedCount = DB::transaction(function () use ($count, $side, $startX, $startY, $user) {
                $now = now();
                $endX = $startX + $side - 1;
                $endY = $startY + $side - 1;

                $hasActiveEditLock = PixelEditLock::query()
                    ->whereBetween('x', [$startX, $endX])
                    ->whereBetween('y', [$startY, $endY])
                    ->where('expires_at', '>', $now)
                    ->exists();

                $hasRecentEdit = Pixel::query()
                    ->whereBetween('x', [$startX, $endX])
                    ->whereBetween('y', [$startY, $endY])
                    ->whereNotNull('updated_by_user_id')
                    ->where('updated_at', '>', $now->copy()->subMinutes(10))
                    ->exists();

                if ($hasActiveEditLock || $hasRecentEdit) {
                    throw new RuntimeException('The selected area contains locked pixels.');
                }

                $expiresAt = $now->copy()->addMinutes(2);
                $editLocks = [];
                for ($y = $startY; $y <= $endY; $y++) {
                    for ($x = $startX; $x <= $endX; $x++) {
                        $editLocks[] = [
                            'x' => $x,
                            'y' => $y,
                            'locked_by_user_id' => $user->id,
                            'expires_at' => $expiresAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                PixelEditLock::query()->insert($editLocks);

                $existingPixels = Pixel::query()
                    ->whereBetween('x', [$startX, $endX])
                    ->whereBetween('y', [$startY, $endY])
                    ->get(['x', 'y', 'color'])
                    ->keyBy(fn ($pixel) => $pixel->x.':'.$pixel->y);

                $colors = ['#ff3b30', '#00d5ff'];
                $updates = [];
                for ($index = 0; $index < $count; $index++) {
                    $x = $startX + ($index % $side);
                    $y = $startY + intdiv($index, $side);
                    $targetColor = $colors[($x + $y) % 2];
                    $existingColor = strtolower($existingPixels->get($x.':'.$y)?->color ?? '');

                    if ($existingColor === $targetColor) {
                        $targetColor = $colors[1 - (($x + $y) % 2)];
                    }

                    $updates[] = [
                        'x' => $x,
                        'y' => $y,
                        'color' => $targetColor,
                        'owner_user_id' => $user->id,
                        'updated_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                Pixel::query()->upsert(
                    $updates,
                    ['x', 'y'],
                    ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']
                );

                PixelEditLock::query()
                    ->where('locked_by_user_id', $user->id)
                    ->whereBetween('x', [$startX, $endX])
                    ->whereBetween('y', [$startY, $endY])
                    ->delete();

                Activity::query()->create([
                    'user_id' => $user->id,
                    'action' => 'painted',
                    'description' => $user->name.' hat 100 Pixel im Simulationslauf gesetzt.',
                ]);

                return count($updates);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$changedCount} Pixel geändert bei ({$startX}, {$startY}) mit Rot/Cyan-Komplementärfarben.");
        $this->info('Die Pixel bleiben für 10 Minuten gesperrt.');

        return self::SUCCESS;
    }

    private function simulateRandomArea(int $count): int
    {
        if ($count < 6 || ! in_array($count % 6, [0, 4], true) || ($count % 6 === 4 && $count < 10)) {
            $this->error('Random mode requires a count divisible by 6, or a count with a final connected 10-pixel cluster.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', 'admin@example.com')->first();
        if (! $user) {
            $this->error('The admin@example.com user does not exist.');

            return self::FAILURE;
        }

        $now = now()->startOfSecond();
        $unavailable = [];

        $recentPixels = Pixel::query()
            ->whereNotNull('updated_by_user_id')
            ->where('updated_at', '>', $now->copy()->subMinutes(10))
            ->get(['x', 'y']);
        foreach ($recentPixels as $pixel) {
            $unavailable[$pixel->x.':'.$pixel->y] = true;
        }

        $activeLocks = PixelEditLock::query()
            ->where('expires_at', '>', $now)
            ->get(['x', 'y']);
        foreach ($activeLocks as $lock) {
            $unavailable[$lock->x.':'.$lock->y] = true;
        }

        $selected = [];
        $attempts = 0;
        $maxAttempts = $count * 50;

        while (count($selected) < $count) {
            $remaining = $count - count($selected);
            $clusterSize = $remaining === 10 ? 10 : 6;
            $vertical = random_int(0, 1) === 1;
            $width = $clusterSize === 10 ? ($vertical ? 2 : 5) : ($vertical ? 2 : 3);
            $height = intdiv($clusterSize, $width);
            $startX = random_int(0, 1024 - $width);
            $startY = random_int(0, 1024 - $height);
            $cluster = [];
            $available = true;

            for ($y = $startY; $y < $startY + $height && $available; $y++) {
                for ($x = $startX; $x < $startX + $width; $x++) {
                    $key = $x.':'.$y;
                    if (isset($unavailable[$key])) {
                        $available = false;
                        break;
                    }
                    $cluster[] = ['x' => $x, 'y' => $y];
                }
            }

            if (! $available) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    $this->error('Could not find enough unlocked pixel clusters.');

                    return self::FAILURE;
                }
                continue;
            }

            foreach ($cluster as $pixel) {
                $key = $pixel['x'].':'.$pixel['y'];
                $selected[$key] = $pixel;
                $unavailable[$key] = true;
            }
        }

        try {
            $changedCount = DB::transaction(function () use ($count, $now, $selected, $user) {
                if (PixelEditLock::query()
                    ->where('locked_by_user_id', $user->id)
                    ->where('expires_at', '>', $now)
                    ->exists()) {
                    throw new RuntimeException('This admin account already has an active editor selection.');
                }

                $expiresAt = $now->copy()->addMinutes(2);
                $lockRows = [];
                foreach ($selected as $pixel) {
                    $lockRows[] = [
                        'x' => $pixel['x'],
                        'y' => $pixel['y'],
                        'locked_by_user_id' => $user->id,
                        'expires_at' => $expiresAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($lockRows, 500) as $chunk) {
                    DB::table('pixel_edit_locks')->insertOrIgnore($chunk);
                }

                $reservedCount = PixelEditLock::query()
                    ->where('locked_by_user_id', $user->id)
                    ->where('expires_at', $expiresAt)
                    ->count();
                if ($reservedCount !== $count) {
                    throw new RuntimeException('Some randomly selected pixels were reserved by another editor. No pixels were changed.');
                }

                $hasRecentPixel = DB::table('pixel_edit_locks as edit_locks')
                    ->join('pixels', function ($join) {
                        $join->on('pixels.x', '=', 'edit_locks.x')
                            ->on('pixels.y', '=', 'edit_locks.y');
                    })
                    ->where('edit_locks.locked_by_user_id', $user->id)
                    ->where('edit_locks.expires_at', $expiresAt)
                    ->whereNotNull('pixels.updated_by_user_id')
                    ->where('pixels.updated_at', '>', $now->copy()->subMinutes(10))
                    ->exists();

                if ($hasRecentPixel) {
                    throw new RuntimeException('A selected pixel was edited moments ago. No pixels were changed.');
                }

                $existingPixels = DB::table('pixel_edit_locks as edit_locks')
                    ->join('pixels', function ($join) {
                        $join->on('pixels.x', '=', 'edit_locks.x')
                            ->on('pixels.y', '=', 'edit_locks.y');
                    })
                    ->where('edit_locks.locked_by_user_id', $user->id)
                    ->where('edit_locks.expires_at', $expiresAt)
                    ->get(['pixels.x', 'pixels.y', 'pixels.color'])
                    ->keyBy(fn ($pixel) => $pixel->x.':'.$pixel->y);

                $palette = ['#ff3b30', '#00d5ff'];
                $updates = [];
                foreach ($selected as $key => $coordinates) {
                    $paletteIndex = ($coordinates['x'] + $coordinates['y']) % 2;
                    $color = $palette[$paletteIndex];
                    if (strtolower($existingPixels->get($key)?->color ?? '') === $color) {
                        $color = $palette[1 - $paletteIndex];
                    }

                    $updates[] = [
                        'x' => $coordinates['x'],
                        'y' => $coordinates['y'],
                        'color' => $color,
                        'owner_user_id' => $user->id,
                        'updated_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($updates, 100) as $chunk) {
                    Pixel::query()->upsert(
                        $chunk,
                        ['x', 'y'],
                        ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']
                    );
                }

                PixelEditLock::query()
                    ->where('locked_by_user_id', $user->id)
                    ->where('expires_at', $expiresAt)
                    ->delete();

                Activity::query()->create([
                    'user_id' => $user->id,
                    'action' => 'painted',
                    'description' => $user->name.' hat '.$count.' zufällig verteilte Pixel geändert.',
                ]);

                return count($updates);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$changedCount} Pixel zufällig verteilt in zusammenhängenden 6er-Clustern (Rot/Cyan) geändert.");
        $this->info('Alle geänderten Pixel bleiben für 10 Minuten gesperrt.');

        return self::SUCCESS;
    }
}
