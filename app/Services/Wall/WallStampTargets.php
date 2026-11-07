<?php

namespace App\Services\Wall;

use App\Models\CommunityProject;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Services\Community\WallReservationMutex;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WallStampTargets
{
    /** Must be called inside the publication transaction. Checks ink, not transparent holes. */
    public function inspect(Request $request, Collection $coordinates): Collection
    {
        $dimensions = DB::table('wall_dimensions')->where('id', 1)->firstOrFail();
        if ($coordinates->contains(fn ($p) => $p['x'] < 0 || $p['y'] < 0 || $p['x'] >= $dimensions->width || $p['y'] >= $dimensions->height)) {
            throw new HttpResponseException(response()->json(['message' => __('Der Stempel liegt außerhalb der Wall.')], 422));
        }
        app(WallReservationMutex::class)->lockCoordinates($coordinates);
        $left = $coordinates->min('x');
        $top = $coordinates->min('y');
        $right = $coordinates->max('x');
        $bottom = $coordinates->max('y');
        $reservations = CommunityProject::query()
            ->where(fn ($q) => $q->whereIn('status', ['scheduled', 'active'])->orWhere(fn ($q) => $q->where('status', 'published')->where('reserved_until', '>', now())))
            ->where('reserved_x', '<=', $right)->whereRaw('reserved_x + width > ?', [$left])
            ->where('reserved_y', '<=', $bottom)->whereRaw('reserved_y + height > ?', [$top])->get();
        $existing = Pixel::query()->whereBetween('x', [$left, $right])->whereBetween('y', [$top, $bottom])
            ->lockForUpdate()->get()->keyBy(fn ($p) => $p->x.':'.$p->y);
        $editLocks = PixelEditLock::query()->whereBetween('x', [$left, $right])->whereBetween('y', [$top, $bottom])
            ->where('expires_at', '>', now())->when($request->user(), fn ($q) => $q->where('locked_by_user_id', '!=', $request->user()->id))
            ->lockForUpdate()->get()->keyBy(fn ($p) => $p->x.':'.$p->y);
        foreach ($coordinates as $point) {
            $key = $point['x'].':'.$point['y'];
            $pixel = $existing->get($key);
            $reserved = $reservations->contains(fn ($p) => $point['x'] >= $p->reserved_x && $point['x'] < $p->reserved_x + $p->width
                && $point['y'] >= $p->reserved_y && $point['y'] < $p->reserved_y + $p->height);
            if ($reserved || $editLocks->has($key) || ($pixel?->updated_by_user_id !== null
                && $pixel->updated_by_user_id !== $request->user()?->id
                && $pixel->updated_at->gt(now()->subMinutes(WallReservationMutex::PIXEL_LOCK_MINUTES)))) {
                throw new HttpResponseException(response()->json(['message' => __('Der Stempel enthält gesperrte oder reservierte Pixel. Es wurde nichts gesetzt.')], 423));
            }
        }

        return $existing;
    }
}
