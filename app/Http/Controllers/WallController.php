<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WallController extends Controller
{
    private const WALL_SIZE = 1024;

    private const WALL_TILE_SIZE = 300;

    private const WALL_EXTENSION_SIZE = 300;

    private const PIXEL_LOCK_MINUTES = 10;

    private const EDIT_LOCK_MINUTES = 2;

    private const REFERENCE_IMAGE_PATH = 'images/current-wall.png';

    private const CURRENT_BACKGROUND_IMAGE_PATH = 'images/current-wall.png';

    public function index()
    {
        return view('wall');
    }

    public function bootstrapDemoData(): void
    {
        $this->seedReferencePixels();
        $this->seedDailySnapshot();
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

    public function tile(Request $request)
    {
        $tile = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ]);
        $dimensions = $this->wallDimensions();
        $x = (int) $tile['x'];
        $y = (int) $tile['y'];

        if (
            $x % self::WALL_TILE_SIZE !== 0
            || $y % self::WALL_TILE_SIZE !== 0
            || $x >= $dimensions->width
            || $y >= $dimensions->height
        ) {
            return response()->json(['message' => 'Ungültige Bildkachel.'], 422);
        }

        $width = min(self::WALL_TILE_SIZE, (int) $dimensions->width - $x);
        $height = min(self::WALL_TILE_SIZE, (int) $dimensions->height - $y);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        Pixel::query()
            ->whereBetween('x', [$x, $x + $width - 1])
            ->whereBetween('y', [$y, $y + $height - 1])
            ->cursor(['x', 'y', 'color'])
            ->each(function ($pixel) use ($canvas, $x, $y) {
                $rgb = $this->hexToRgb($pixel->color);
                $color = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
                imagesetpixel($canvas, (int) $pixel->x - $x, (int) $pixel->y - $y, $color);
            });

        ob_start();
        imagepng($canvas);
        $image = ob_get_clean();
        imagedestroy($canvas);

        return response($image, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function extend(Request $request)
    {
        $data = $request->validate([
            'axis' => ['required', 'in:x,y'],
        ]);

        $dimensions = DB::transaction(function () use ($data) {
            $wall = DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->first();
            $width = (int) $wall->width;
            $height = (int) $wall->height;
            if (
                ($data['axis'] === 'x' && $width > 4294966995)
                || ($data['axis'] === 'y' && $height > 4294966995)
            ) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Die Pixelwand hat die maximale Koordinatengröße erreicht.',
                ], 422));
            }
            $now = now();
            $rows = [];

            if ($data['axis'] === 'x') {
                for ($y = 0; $y < $height; $y++) {
                    for ($x = $width; $x < $width + self::WALL_EXTENSION_SIZE; $x++) {
                        $rows[] = $this->whitePixelRow($x, $y, $now);
                    }
                    if (count($rows) >= 2000) {
                        Pixel::query()->insert($rows);
                        $rows = [];
                    }
                }
                $width += self::WALL_EXTENSION_SIZE;
            } else {
                for ($y = $height; $y < $height + self::WALL_EXTENSION_SIZE; $y++) {
                    for ($x = 0; $x < $width; $x++) {
                        $rows[] = $this->whitePixelRow($x, $y, $now);
                    }
                    if (count($rows) >= 2000) {
                        Pixel::query()->insert($rows);
                        $rows = [];
                    }
                }
                $height += self::WALL_EXTENSION_SIZE;
            }

            if ($rows !== []) {
                Pixel::query()->insert($rows);
            }

            DB::table('wall_dimensions')->where('id', 1)->update([
                'width' => $width,
                'height' => $height,
                'updated_at' => $now,
            ]);

            return (object) ['width' => $width, 'height' => $height];
        });

        return response()->json([
            'success' => true,
            'width' => $dimensions->width,
            'height' => $dimensions->height,
            'axis' => $data['axis'],
        ]);
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
            'width' => ['required', 'integer', 'min:5', 'max:1024'],
            'height' => ['required', 'integer', 'min:5', 'max:1024'],
        ]);
        $dimensions = $this->wallDimensions();

        if ($dimensions->width < $area['x'] + $area['width'] || $dimensions->height < $area['y'] + $area['height']) {
            return response()->json(['message' => 'Die Auswahl liegt außerhalb der Pixelwand.'], 422);
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
                    'message' => 'Mindestens ein Pixel ist nach einer Bearbeitung noch gesperrt.',
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
                    'message' => 'Ein Teil der Auswahl wird gerade bearbeitet.',
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
            'width' => ['required', 'integer', 'min:5', 'max:1024'],
            'height' => ['required', 'integer', 'min:5', 'max:1024'],
        ]);
        $dimensions = $this->wallDimensions();

        if ($dimensions->width < $area['x'] + $area['width'] || $dimensions->height < $area['y'] + $area['height']) {
            return response()->json(['message' => 'Die Auswahl liegt außerhalb der Pixelwand.'], 422);
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
                    'user' => $entry->user?->name ?? 'System',
                    'description' => $entry->description,
                    'created_at' => $entry->created_at->diffForHumans(),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $batchPixels = $request->input('pixels');
        $isBatch = is_array($batchPixels) && count($batchPixels) > 0;

        if ($isBatch) {
            $validatedPixels = $request->validate([
                'pixels' => ['required', 'array'],
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
                return response()->json(['message' => 'Das Pixel liegt außerhalb der Pixelwand.'], 422);
            }
        }

        return DB::transaction(function () use ($request, $validatedPixels, $isBatch) {
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
                    'message' => 'Mindestens ein Pixel ist bereits bearbeitet und noch gesperrt.',
                    'locked_pixels' => $lockedPixels,
                ], 423);
            }

            $ownedLocks = PixelEditLock::query()
                ->where('locked_by_user_id', $request->user()->id)
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
                    'message' => 'Die Bearbeitungsreservierung fehlt oder ist abgelaufen. Bitte wähle den Bereich erneut.',
                ], 423);
            }

            foreach ($validatedPixels as $pixelData) {
                Pixel::updateOrCreate(
                    ['x' => $pixelData['x'], 'y' => $pixelData['y']],
                    [
                        'color' => $pixelData['color'],
                        'owner_user_id' => $request->user()->id,
                        'updated_by_user_id' => $request->user()->id,
                    ]
                );
            }

            if (! $isBatch) {
                Activity::create([
                    'user_id' => $request->user()->id,
                    'action' => 'painted',
                    'description' => $request->user()->name.' hat ein Pixel gesetzt.',
                ]);
            }

            return response()->json([
                'success' => true,
                'count' => count($validatedPixels),
                'locked_until' => now()->addMinutes(self::PIXEL_LOCK_MINUTES)->toIso8601String(),
            ]);
        });
    }

    public function backgroundImage(Request $request)
    {
        $data = $request->input('image');

        if (! empty($data) && is_string($data) && str_contains($data, 'data:image/')) {
            $content = explode(',', $data, 2)[1] ?? null;

            if ($content === null) {
                return response()->json(['success' => false, 'message' => 'Invalid image payload.'], 422);
            }

            $path = public_path(self::CURRENT_BACKGROUND_IMAGE_PATH);

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            file_put_contents($path, base64_decode($content));

            return response()->json([
                'success' => true,
                'url' => '/'.self::CURRENT_BACKGROUND_IMAGE_PATH,
            ]);
        }

        $dimensions = $this->wallDimensions();
        $width = min(1600, (int) $dimensions->width);
        $height = min(1600, (int) $dimensions->height);
        $pixels = Pixel::query()
            ->where('x', '<', $width)
            ->where('y', '<', $height)
            ->orderBy('y')
            ->orderBy('x')
            ->get();
        $canvas = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);

        foreach ($pixels as $pixel) {
            $color = $this->hexToRgb($pixel->color);
            $paint = imagecolorallocate($canvas, $color['r'], $color['g'], $color['b']);
            imagesetpixel($canvas, (int) $pixel->x, (int) $pixel->y, $paint);
        }

        $path = public_path(self::CURRENT_BACKGROUND_IMAGE_PATH);
        imagepng($canvas, $path);
        imagedestroy($canvas);

        return response()->json([
            'success' => true,
            'url' => '/'.self::CURRENT_BACKGROUND_IMAGE_PATH,
        ]);
    }

    public function backgroundImageVersion()
    {
        $path = public_path(self::CURRENT_BACKGROUND_IMAGE_PATH);

        return response()->json([
            'version' => file_exists($path) ? filemtime($path) : null,
        ]);
    }

    private function seedReferencePixels(): void
    {
        if (Pixel::count() > 0) {
            return;
        }

        $referencePath = public_path(self::REFERENCE_IMAGE_PATH);
        if (! file_exists($referencePath)) {
            $fallbackPath = public_path('images/trump-portrait-2025.jpg');
            if (! file_exists($fallbackPath)) {
                return;
            }
            $referencePath = $fallbackPath;
        }

        $imageData = @file_get_contents($referencePath);
        if ($imageData === false) {
            return;
        }

        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            return;
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scaledHeight = (int) round($sourceHeight * (self::WALL_SIZE / $sourceWidth));

        $scaled = imagecreatetruecolor(self::WALL_SIZE, $scaledHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled(
            $scaled,
            $image,
            0,
            0,
            0,
            0,
            self::WALL_SIZE,
            $scaledHeight,
            $sourceWidth,
            $sourceHeight
        );

        $cropped = imagecreatetruecolor(self::WALL_SIZE, self::WALL_SIZE);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagecopy(
            $cropped,
            $scaled,
            0,
            0,
            0,
            0,
            self::WALL_SIZE,
            min(self::WALL_SIZE, imagesy($scaled))
        );

        $batch = [];
        $timestamp = now();
        for ($y = 0; $y < self::WALL_SIZE; $y++) {
            for ($x = 0; $x < self::WALL_SIZE; $x++) {
                $rgba = imagecolorat($cropped, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($alpha > 0 && $alpha < 127) {
                    $red = (int) (($red * (127 - $alpha)) / 127);
                    $green = (int) (($green * (127 - $alpha)) / 127);
                    $blue = (int) (($blue * (127 - $alpha)) / 127);
                }

                $batch[] = [
                    'x' => $x,
                    'y' => $y,
                    'color' => $this->rgbToHex($red, $green, $blue),
                    'owner_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($batch) >= 2000) {
                    Pixel::query()->insert($batch);
                    $batch = [];
                    gc_collect_cycles();
                }
            }
        }

        if (! empty($batch)) {
            Pixel::query()->insert($batch);
        }

        imagedestroy($image);
        imagedestroy($scaled);
        imagedestroy($cropped);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function wallDimensions(): object
    {
        return DB::table('wall_dimensions')->where('id', 1)->first();
    }

    private function whitePixelRow(int $x, int $y, $timestamp): array
    {
        return [
            'x' => $x,
            'y' => $y,
            'color' => '#ffffff',
            'owner_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function seedDailySnapshot(): void
    {
        if (Snapshot::whereDate('created_at', today())->exists()) {
            return;
        }

        Snapshot::create([
            'label' => 'Daily Snapshot',
            'summary' => [
                'painted_pixels' => Pixel::count(),
                'changes_today' => Activity::whereDate('created_at', today())->count(),
            ],
            'image_path' => null,
        ]);
    }
}
