<?php

namespace App\Services\Community;

use App\Models\CommunityProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WallReservationMutex
{
    public const PIXEL_LOCK_MINUTES = 10;

    private const TILE_SIZE = 300;

    public function activePixelLocks(int $x, int $y, int $width, int $height): Collection
    {
        $now = now();
        $edited = DB::table('pixels')
            ->whereBetween('x', [$x, $x + $width - 1])
            ->whereBetween('y', [$y, $y + $height - 1])
            ->whereNotNull('updated_by_user_id')
            ->where('updated_at', '>', $now->copy()->subMinutes(self::PIXEL_LOCK_MINUTES))
            ->get(['x', 'y', 'updated_at'])
            ->map(fn ($pixel) => [
                'x' => (int) $pixel->x, 'y' => (int) $pixel->y,
                'locked_until' => Carbon::parse($pixel->updated_at)->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String(),
            ]);
        $selected = DB::table('pixel_edit_locks')
            ->whereBetween('x', [$x, $x + $width - 1])
            ->whereBetween('y', [$y, $y + $height - 1])
            ->where('expires_at', '>', $now)
            ->get(['x', 'y', 'expires_at'])
            ->map(fn ($pixel) => [
                'x' => (int) $pixel->x, 'y' => (int) $pixel->y,
                'locked_until' => Carbon::parse($pixel->expires_at)->toIso8601String(),
            ]);

        return $edited->merge($selected)->sortByDesc('locked_until')
            ->unique(fn ($pixel) => $pixel['x'].':'.$pixel['y'])->values();
    }

    public function reservationAt(int $x, int $y, int $width, int $height): ?CommunityProject
    {
        return CommunityProject::query()
            ->where(fn ($query) => $query->whereIn('status', ['scheduled', 'active'])
                ->orWhere(fn ($query) => $query->where('status', 'published')->where('reserved_until', '>', now())))
            ->where('reserved_x', '<', $x + $width)->whereRaw('reserved_x + width > ?', [$x])
            ->where('reserved_y', '<', $y + $height)->whereRaw('reserved_y + height > ?', [$y])
            ->with('group:id,name')->first();
    }

    public function lockCoordinates(iterable $coordinates): void
    {
        $tiles = [];
        foreach ($coordinates as $coordinate) {
            $tiles[intdiv((int) $coordinate['x'], self::TILE_SIZE).':'.intdiv((int) $coordinate['y'], self::TILE_SIZE)] = [
                'tile_x' => intdiv((int) $coordinate['x'], self::TILE_SIZE),
                'tile_y' => intdiv((int) $coordinate['y'], self::TILE_SIZE),
            ];
        }

        $this->lockTiles(array_values($tiles));
    }

    public function lockRegion(int $x, int $y, int $width, int $height): void
    {
        $tiles = [];
        $lastTileX = intdiv($x + $width - 1, self::TILE_SIZE);
        $lastTileY = intdiv($y + $height - 1, self::TILE_SIZE);
        for ($tileX = intdiv($x, self::TILE_SIZE); $tileX <= $lastTileX; $tileX++) {
            for ($tileY = intdiv($y, self::TILE_SIZE); $tileY <= $lastTileY; $tileY++) {
                $tiles[] = ['tile_x' => $tileX, 'tile_y' => $tileY];
            }
        }

        $this->lockTiles($tiles);
    }

    private function lockTiles(array $tiles): void
    {
        usort($tiles, fn (array $left, array $right) => [$left['tile_x'], $left['tile_y']] <=> [$right['tile_x'], $right['tile_y']]);

        foreach ($tiles as $tile) {
            DB::table('community_wall_tile_mutexes')->insertOrIgnore($tile);
        }

        if ($tiles === []) {
            return;
        }

        DB::table('community_wall_tile_mutexes')
            ->where(function ($query) use ($tiles) {
                foreach ($tiles as $tile) {
                    $query->orWhere(fn ($query) => $query
                        ->where('tile_x', $tile['tile_x'])
                        ->where('tile_y', $tile['tile_y']));
                }
            })
            ->orderBy('tile_x')
            ->orderBy('tile_y')
            ->lockForUpdate()
            ->get();
    }
}
