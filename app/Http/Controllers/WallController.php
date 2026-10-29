<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\Snapshot;
use App\Services\Wall\PixelQuota;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WallController extends Controller
{
    public function __construct(private PixelQuota $quota) {}

    private const WALL_TILE_SIZE = 300;

    private const PIXEL_LOCK_MINUTES = 10;

    private const EDIT_LOCK_MINUTES = 2;

    public function index()
    {
        return view('wall', [
            'shareImage' => ($path = Snapshot::latest()->value('image_path')) ? asset($path) : null,
        ]);
    }

    public function pixels(Request $request)
    {
        $x = $request->query('x');
        $y = $request->query('y');
        $width = $request->query('width');
        $height = $request->query('height');

        $query = Pixel::query()->select('x', 'y', 'color', 'updated_by_user_id', 'updated_at');

        if ($x !== null && $y !== null && $width !== null && $height !== null) {
            $query->whereBetween('x', [(int) $x, (int) $x + (int) $width - 1])
                ->whereBetween('y', [(int) $y, (int) $y + (int) $height - 1]);
        }

        $pixels = $query->get()->map(function ($pixel) {
            $lockedUntil = $pixel->updated_by_user_id !== null
                ? $pixel->updated_at?->copy()->addMinutes(self::PIXEL_LOCK_MINUTES)
                : null;

            return [
                'x' => (int) $pixel->x,
                'y' => (int) $pixel->y,
                'color' => $pixel->color,
                'locked_until' => $lockedUntil?->toIso8601String(),
            ];
        });

        return response()->json([
            'pixels' => $pixels,
        ]);
    }

    public function dimensions()
    {
        $dimensions = $this->wallDimensions();

        return response()->json([
            'width' => (int) $dimensions->width,
            'height' => (int) $dimensions->height,
            'tile_size' => self::WALL_TILE_SIZE,
        ]);
    }

    public function pixelStatus(Request $request)
    {
        return response()->json($this->quota->status($request));
    }

    private function quotaDenied(array $status)
    {
        return response()->json($status + [
            'success' => false,
            'message' => $status['limit'] === 'guest'
                ? __('Dein Gast-Vorrat von :max Pixeln ist aufgebraucht. Melde dich an, um alle :seconds Sekunden ein Pixel zu setzen.', ['max' => $status['max_free'], 'seconds' => $this->quota->regenSeconds()])
                : __('Dein Vorrat ist leer. Alle :seconds Sekunden wächst ein Pixel nach (maximal :max). Mit Extra-Pixeln geht es sofort weiter.', ['seconds' => $this->quota->regenSeconds(), 'max' => $status['max_free']]),
        ], 429);
    }

    public function pixelLocks(Request $request)
    {
        $editedLocks = Pixel::query()
            ->whereNotNull('updated_by_user_id')
            ->where('updated_at', '>', now()->subMinutes(self::PIXEL_LOCK_MINUTES))
            ->get(['x', 'y', 'updated_at'])
            ->map(function ($pixel) {
                return [
                    'x' => (int) $pixel->x,
                    'y' => (int) $pixel->y,
                    'locked_until' => $pixel->updated_at->copy()->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String(),
                ];
            })
            ->values();

        $selectionLocks = PixelEditLock::query()
            ->where('expires_at', '>', now())
            ->when($request->user(), fn ($query, $user) => $query->where('locked_by_user_id', '!=', $user->id))
            ->get(['x', 'y', 'expires_at'])
            ->map(fn ($lock) => [
                'x' => (int) $lock->x,
                'y' => (int) $lock->y,
                'locked_until' => $lock->expires_at->toIso8601String(),
            ]);

        $lockedPixels = $editedLocks->merge($selectionLocks)
            ->unique(fn ($pixel) => $pixel['x'].':'.$pixel['y'])
            ->values();

        return response()->json(['locked_pixels' => $lockedPixels]);
    }

    public function lockPixelArea(Request $request)
    {
        $area = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'size:1'],
            'height' => ['required', 'integer', 'size:1'],
        ]);
        $dimensions = $this->wallDimensions();

        if ($dimensions->width < $area['x'] + $area['width'] || $dimensions->height < $area['y'] + $area['height']) {
            return response()->json(['message' => __('Die Auswahl liegt außerhalb der Pixelwand.')], 422);
        }

        $quotaStatus = $this->quota->status($request);
        if (! $quotaStatus['can_edit']) {
            return $this->quotaDenied($quotaStatus);
        }

        return DB::transaction(function () use ($request, $area) {
            $now = now();
            $expiresAt = $now->copy()->addMinutes(self::EDIT_LOCK_MINUTES);
            PixelEditLock::query()->where('expires_at', '<=', $now)->delete();

            $recentlyEdited = Pixel::query()
                ->whereBetween('x', [$area['x'], $area['x'] + $area['width'] - 1])
                ->whereBetween('y', [$area['y'], $area['y'] + $area['height'] - 1])
                ->whereNotNull('updated_by_user_id')
                ->where('updated_at', '>', $now->copy()->subMinutes(self::PIXEL_LOCK_MINUTES))
                ->get(['x', 'y', 'updated_at'])
                ->map(fn ($pixel) => [
                    'x' => (int) $pixel->x,
                    'y' => (int) $pixel->y,
                    'locked_until' => $pixel->updated_at->copy()->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String(),
                ]);

            if ($recentlyEdited->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Mindestens ein Pixel ist nach einer Bearbeitung noch gesperrt.'),
                    'locked_pixels' => $recentlyEdited->values(),
                ], 423);
            }

            $lockRows = [];
            for ($y = $area['y']; $y < $area['y'] + $area['height']; $y++) {
                for ($x = $area['x']; $x < $area['x'] + $area['width']; $x++) {
                    $lockRows[] = [
                        'x' => $x,
                        'y' => $y,
                        'locked_by_user_id' => $request->user()->id,
                        'expires_at' => $expiresAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($lockRows) === 200) {
                        DB::table('pixel_edit_locks')->insertOrIgnore($lockRows);
                        $lockRows = [];
                    }
                }
            }

            if ($lockRows !== []) {
                DB::table('pixel_edit_locks')->insertOrIgnore($lockRows);
            }

            $foreignLocks = PixelEditLock::query()
                ->whereBetween('x', [$area['x'], $area['x'] + $area['width'] - 1])
                ->whereBetween('y', [$area['y'], $area['y'] + $area['height'] - 1])
                ->where('expires_at', '>', $now)
                ->where('locked_by_user_id', '!=', $request->user()->id)
                ->lockForUpdate()
                ->get(['x', 'y', 'expires_at'])
                ->map(fn ($lock) => [
                    'x' => (int) $lock->x,
                    'y' => (int) $lock->y,
                    'locked_until' => $lock->expires_at->toIso8601String(),
                ])
                ->values();

            if ($foreignLocks->isNotEmpty()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => __('Ein Teil der Auswahl wird gerade bearbeitet.'),
                    'locked_pixels' => $foreignLocks,
                ], 423));
            }

            PixelEditLock::query()
                ->where('locked_by_user_id', $request->user()->id)
                ->whereBetween('x', [$area['x'], $area['x'] + $area['width'] - 1])
                ->whereBetween('y', [$area['y'], $area['y'] + $area['height'] - 1])
                ->update(['expires_at' => $expiresAt, 'updated_at' => $now]);

            return response()->json([
                'success' => true,
                'expires_at' => $expiresAt->toIso8601String(),
                'count' => $area['width'] * $area['height'],
            ]);
        });
    }

    public function releasePixelAreaLock(Request $request)
    {
        $area = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'size:1'],
            'height' => ['required', 'integer', 'size:1'],
        ]);
        $dimensions = $this->wallDimensions();

        if ($dimensions->width < $area['x'] + $area['width'] || $dimensions->height < $area['y'] + $area['height']) {
            return response()->json(['message' => __('Die Auswahl liegt außerhalb der Pixelwand.')], 422);
        }

        PixelEditLock::query()
            ->where('locked_by_user_id', $request->user()->id)
            ->whereBetween('x', [$area['x'], $area['x'] + $area['width'] - 1])
            ->whereBetween('y', [$area['y'], $area['y'] + $area['height'] - 1])
            ->delete();

        return response()->json(['success' => true]);
    }

    public function activity()
    {
        return response()->json([
            'activity' => Activity::with('user')->latest()->limit(8)->get()->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'user' => $entry->user?->name ?? __('System'),
                    'description' => $entry->localizedDescription(),
                    'created_at' => $entry->created_at->locale(app()->getLocale())->diffForHumans(),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $isBatch = array_key_exists('pixels', $request->all());
        if ($isBatch) {
            $validatedPixels = $request->validate([
                'pixels' => ['required', 'array', 'size:1'],
                'pixels.*.x' => ['required', 'integer', 'min:0'],
                'pixels.*.y' => ['required', 'integer', 'min:0'],
                'pixels.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ])['pixels'];
        } else {
            $validatedPixels = [$request->validate([
                'x' => ['required', 'integer', 'min:0'],
                'y' => ['required', 'integer', 'min:0'],
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ])];
        }

        $dimensions = $this->wallDimensions();
        foreach ($validatedPixels as $pixelData) {
            if ($pixelData['x'] >= $dimensions->width || $pixelData['y'] >= $dimensions->height) {
                return response()->json(['message' => __('Das Pixel liegt außerhalb der Pixelwand.')], 422);
            }
        }

        $user = $request->user();
        $savePixels = function () use ($validatedPixels, $isBatch, $user) {
            return DB::transaction(function () use ($validatedPixels, $isBatch, $user) {
                $coordinates = collect($validatedPixels)->unique(fn ($pixel) => $pixel['x'].':'.$pixel['y']);
                $lockedPixels = Pixel::query()
                    ->whereNotNull('updated_by_user_id')
                    ->where('updated_at', '>', now()->subMinutes(self::PIXEL_LOCK_MINUTES))
                    ->where(function ($query) use ($coordinates) {
                        foreach ($coordinates as $pixel) {
                            $query->orWhere(function ($query) use ($pixel) {
                                $query->where('x', $pixel['x'])->where('y', $pixel['y']);
                            });
                        }
                    })
                    ->lockForUpdate()
                    ->get(['x', 'y', 'updated_at'])
                    ->map(function ($pixel) {
                        return [
                            'x' => (int) $pixel->x,
                            'y' => (int) $pixel->y,
                            'locked_until' => $pixel->updated_at->copy()->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String(),
                        ];
                    })
                    ->values();

                if ($lockedPixels->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Mindestens ein Pixel ist bereits bearbeitet und noch gesperrt.'),
                        'locked_pixels' => $lockedPixels,
                    ], 423);
                }

                if ($user !== null) {
                    $ownedLocks = PixelEditLock::query()
                        ->where('locked_by_user_id', $user->id)
                        ->where('expires_at', '>', now())
                        ->where(function ($query) use ($coordinates) {
                            foreach ($coordinates as $pixel) {
                                $query->orWhere(function ($query) use ($pixel) {
                                    $query->where('x', $pixel['x'])->where('y', $pixel['y']);
                                });
                            }
                        })
                        ->lockForUpdate()
                        ->get(['x', 'y'])
                        ->keyBy(fn ($pixel) => $pixel->x.':'.$pixel->y);

                    $missingLocks = $coordinates->filter(fn ($pixel) => ! $ownedLocks->has($pixel['x'].':'.$pixel['y']));
                    if ($missingLocks->isNotEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => __('Die Bearbeitungsreservierung fehlt oder ist abgelaufen. Bitte wähle den Pixel erneut.'),
                        ], 423);
                    }
                }

                if ($user !== null) {
                    $denied = $this->quota->consume($user);
                    if ($denied !== null) {
                        return $this->quotaDenied($denied);
                    }
                }

                foreach ($validatedPixels as $pixelData) {
                    Pixel::updateOrCreate(
                        ['x' => $pixelData['x'], 'y' => $pixelData['y']],
                        [
                            'color' => $pixelData['color'],
                            'owner_user_id' => $user?->id,
                            'updated_by_user_id' => $user?->id,
                        ]
                    );
                }

                if ($user !== null && ! $isBatch) {
                    Activity::create([
                        'user_id' => $user->id,
                        'action' => 'painted',
                        'type' => 'pixel_set',
                        'params' => ['name' => $user->name],
                        'description' => $user->name.' hat ein Pixel gesetzt.',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'count' => count($validatedPixels),
                    'locked_until' => $user !== null
                        ? now()->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String()
                        : null,
                ]);
            });
        };

        if ($user !== null) {
            return $savePixels();
        }

        $rateLimitKey = $this->quota->guestKey($request);

        return Cache::lock($rateLimitKey.':lock', 10)->block(5, function () use ($savePixels, $request) {
            $status = $this->quota->status($request);
            if (! $status['can_edit']) {
                return $this->quotaDenied($status);
            }

            $response = $savePixels();
            if ($response->isSuccessful()) {
                $this->quota->recordGuestPixel($request);
            }

            return $response;
        });
    }

    private function wallDimensions(): object
    {
        return DB::table('wall_dimensions')->where('id', 1)->first();
    }
}
