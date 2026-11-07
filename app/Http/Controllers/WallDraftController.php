<?php

namespace App\Http\Controllers;

use App\Models\Pixel;
use App\Models\User;
use App\Models\WallArtwork;
use App\Models\WallDraft;
use App\Services\Analytics;
use App\Services\Wall\PixelQuota;
use App\Services\Wall\WallDrafts;
use App\Services\Wall\WallStampTargets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WallDraftController extends Controller
{
    public function index(Request $request, WallDrafts $drafts, PixelQuota $quota)
    {
        $cookie = null;
        if ($request->user()) {
            $drafts->claim($request);
            // Serialize initial creation across tabs without preventing multiple imported drafts.
            $items = DB::transaction(function () use ($request) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $items = WallDraft::where('user_id', $request->user()->id)->latest('updated_at')->orderByDesc('id')->get();
                if ($items->isEmpty()) {
                    $items->push(WallDraft::create(['user_id' => $request->user()->id, 'pixels' => [], 'paid_cells' => []]));
                }

                return $items;
            });
        } else {
            $token = $request->cookie(WallDrafts::COOKIE);
            $hash = $drafts->guestHash($request);
            if ($hash === null) {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $cookie = cookie(WallDrafts::COOKIE, $token, 0, '/', null, $request->isSecure(), true, false, 'lax');
            }
            $draft = WallDraft::firstOrCreate(['guest_hash' => $hash], [
                'pixels' => [], 'paid_cells' => [], 'expires_at' => now()->addDays(30),
            ]);
            if ($draft->user_id !== null || $draft->expires_at->isPast()) {
                return response()->json(['message' => __('Der Gastentwurf ist abgelaufen.')], 410)
                    ->withCookie(cookie(WallDrafts::COOKIE, '', -1, '/', null, $request->isSecure(), true, false, 'lax'));
            }
            $items = collect([$draft]);
        }
        $response = response()->json(['drafts' => $items->map(fn ($draft) => $drafts->payload($draft))->values(), 'quota' => $quota->status($request)])
            ->header('Cache-Control', 'private, no-store');

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    public function update(Request $request, int $draft, WallDrafts $drafts, PixelQuota $quota)
    {
        abort_if($request->user()?->isBanned(), 403, __('Dein Konto wurde gesperrt. Du kannst keine Pixel mehr setzen.'));
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'title' => ['present', 'nullable', 'string', 'max:80'],
            'changes' => ['present', 'array', 'max:1000'],
            'changes.*' => ['required', 'array:index,color'],
            'changes.*.index' => ['required', 'integer', 'between:0,999', 'distinct'],
            'changes.*.color' => ['present', 'nullable', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
        ]);
        $save = fn () => DB::transaction(function () use ($request, $draft, $drafts, $quota, $data) {
            $model = $drafts->owned($request, $draft);
            if ($model->version !== $data['version']) {
                return response()->json(['message' => __('Der Entwurf wurde in einem anderen Fenster geändert. Der aktuelle Stand wurde geladen.'), 'draft' => $drafts->payload($model)], 409);
            }
            $pixels = $model->pixels;
            $paid = array_fill_keys($model->paid_cells, true);
            $cost = 0;
            foreach ($data['changes'] as $change) {
                $index = $change['index'];
                if ($change['color'] !== null) {
                    if (! isset($paid[$index])) {
                        $paid[$index] = true;
                        $cost++;
                    }
                    $pixels[$index] = strtolower($change['color']);
                } else {
                    unset($pixels[$index]);
                }
            }
            if ($cost > 0) {
                $denied = $request->user() ? $quota->consume($request->user(), $cost) : null;
                $status = $denied ?? $quota->status($request);
                if ($denied !== null || (! $request->user() && $status['free'] < $cost)) {
                    return response()->json(['message' => __('Nicht genügend Pixel. Der letzte Pinselstrich wurde nicht gespeichert.'), 'quota' => $status, 'draft' => $drafts->payload($model)], 429);
                }
            }
            $model->update([
                'pixels' => $pixels, 'paid_cells' => array_keys($paid), 'title' => $data['title'] ?? '',
                'version' => $model->version + 1,
                'expires_at' => $request->user() ? null : now()->addDays(30),
            ]);
            $request->user()?->refresh();

            return response()->json(['draft' => $drafts->payload($model), 'cost' => $cost, 'quota' => $quota->status($request)]);
        });
        if ($request->user()) {
            return $save();
        }

        return Cache::lock($quota->guestKey($request).':lock', 10)->block(5, function () use ($save, $request, $quota) {
            $response = $save();
            if ($response->isSuccessful()) {
                $payload = $response->getData(true);
                $quota->recordGuestPixel($request, $payload['cost']);
                $payload['quota'] = $quota->status($request);
                $response->setData($payload);
            }

            return $response;
        });
    }

    public function publish(Request $request, int $draft, WallDrafts $drafts, WallStampTargets $targets, PixelQuota $quota, Analytics $analytics)
    {
        abort_if($request->user()?->isBanned(), 403, __('Dein Konto wurde gesperrt. Du kannst keine Pixel mehr setzen.'));
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'x' => ['required', 'integer', 'between:0,2147483647'],
            'y' => ['required', 'integer', 'between:0,2147483647'],
        ]);

        return DB::transaction(function () use ($request, $draft, $drafts, $targets, $quota, $analytics, $data) {
            $model = $drafts->owned($request, $draft);
            if ($model->version !== $data['version']) {
                return response()->json(['message' => __('Der Entwurf wurde in einem anderen Fenster geändert. Der aktuelle Stand wurde geladen.'), 'draft' => $drafts->payload($model)], 409);
            }
            abort_if(count($model->pixels) === 0, 422, __('Dein Entwurf ist noch leer.'));
            $points = collect($model->pixels)->map(fn ($color, $index) => [
                'x' => $data['x'] + (int) $index % 20, 'y' => $data['y'] + intdiv((int) $index, 20), 'color' => $color,
            ])->values();
            $existing = $targets->inspect($request, $points);
            $changed = $points->filter(fn ($p) => strtolower($existing->get($p['x'].':'.$p['y'])?->color ?? '#ffffff') !== $p['color']);
            $now = now();
            if ($changed->isNotEmpty()) {
                Pixel::upsert($changed->map(fn ($p) => $p + [
                    'owner_user_id' => $request->user()?->id, 'updated_by_user_id' => $request->user()?->id,
                    'created_at' => $now, 'updated_at' => $now,
                ])->all(), ['x', 'y'], ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']);
                $analytics->recordPixels($request, $changed->count());
            }
            if ($request->user()) {
                WallArtwork::create([
                    'user_id' => $request->user()->id, 'title' => $model->title ?: __('Mein Pixelbild'),
                    'pixels' => $model->pixels, 'wall_x' => $data['x'], 'wall_y' => $data['y'],
                ]);
            }
            $model->update(['pixels' => [], 'paid_cells' => [], 'title' => '', 'version' => $model->version + 1]);

            return response()->json(['pixels' => $points, 'draft' => $drafts->payload($model), 'quota' => $quota->status($request)]);
        });
    }
}
