<?php

namespace App\Http\Controllers;

use App\Models\Pixel;
use App\Services\Analytics;
use App\Services\Wall\PixelGlyphs;
use App\Services\Wall\PixelQuota;
use App\Services\Wall\WallStampTargets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WallGlyphController extends Controller
{
    public function __invoke(Request $request, PixelGlyphs $glyphs, PixelQuota $quota, WallStampTargets $targets, Analytics $analytics)
    {
        $data = $request->validate([
            'font' => ['required', Rule::in(PixelGlyphs::FONTS)],
            'size' => ['required', 'integer', Rule::in($glyphs->catalog()['sizes'])],
            'letter' => ['required', 'string', 'regex:/\A[A-Z0-9]\z/'],
            'x' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'y' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'cost' => ['required', 'integer', 'min:0', 'max:49'],
        ]);
        abort_if($request->user()?->isBanned(), 403, __('Dein Konto wurde gesperrt. Du kannst keine Pixel mehr setzen.'));
        $coordinates = collect($glyphs->glyph($data['font'], $data['size'], $data['letter']))
            ->map(fn ($point) => ['x' => $data['x'] + $point[0], 'y' => $data['y'] + $point[1]]);
        $save = function () use ($request, $data, $coordinates, $targets, $quota, $analytics) {
            return DB::transaction(function () use ($request, $data, $coordinates, $targets, $quota, $analytics) {
                $existing = $targets->inspect($request, $coordinates);
                $changed = $coordinates->filter(fn ($p) => strtolower($existing->get($p['x'].':'.$p['y'])?->color ?? '#ffffff') !== strtolower($data['color']));
                $cost = $changed->count();
                if ($cost !== $data['cost']) {
                    return response()->json([
                        'message' => __('Die Pixelkosten haben sich geändert. Bitte prüfe die Vorschau erneut.'),
                        'cost' => $cost,
                        'current_pixels' => $coordinates->map(fn ($p) => $p + [
                            'color' => $existing->get($p['x'].':'.$p['y'])?->color ?? '#ffffff',
                        ])->values(),
                    ], 409);
                }
                if ($cost > 0) {
                    $denied = $request->user() ? $quota->consume($request->user(), $cost) : null;
                    $status = $request->user() ? $denied : $quota->status($request);
                    if ($denied !== null || ($status !== null && $status['free'] < $cost)) {
                        return response()->json(['message' => __('Dein Pixelvorrat reicht für dieses Zeichen nicht aus.'), 'quota' => $status, 'cost' => $cost], 429);
                    }
                    $now = now();
                    Pixel::upsert($changed->map(fn ($p) => $p + [
                        'color' => strtolower($data['color']), 'owner_user_id' => $request->user()?->id,
                        'updated_by_user_id' => $request->user()?->id, 'created_at' => $now, 'updated_at' => $now,
                    ])->values()->all(), ['x', 'y'], ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']);
                    $analytics->recordPixels($request, $cost);
                }
                $request->user()?->refresh();

                return response()->json(['success' => true, 'count' => $cost, 'pixels' => $changed->values(), 'quota' => $quota->status($request)]);
            });
        };
        if ($request->user()) {
            return $save();
        }

        return Cache::lock($quota->guestKey($request).':lock', 10)->block(5, function () use ($save, $request, $quota) {
            $response = $save();
            if ($response->isSuccessful()) {
                $payload = $response->getData(true);
                $quota->recordGuestPixel($request, $payload['count']);
                $payload['quota'] = $quota->status($request);
                $response->setData($payload);
            }

            return $response;
        });
    }
}
