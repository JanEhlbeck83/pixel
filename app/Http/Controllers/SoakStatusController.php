<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SoakStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(app()->environment('staging') && config('soak.enabled')
            && in_array(config('mail.default'), ['log', 'array'], true), 404);
        $path = config('soak.manifest');
        abort_unless(is_file($path), 404);
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(collect($manifest['users'])->contains('id', $request->user()->id), 403);
        abort_unless(Carbon::parse($manifest['expires_at'])->isFuture(), 410);
        abort_unless(User::query()->count() === count($manifest['users'])
            && rtrim(config('app.url'), '/') === $manifest['target'], 409);

        return response()->json([
            'run_id' => $manifest['run_id'], 'target' => $manifest['target'],
            'expires_at' => $manifest['expires_at'], 'user_id' => $request->user()->id,
            'wall' => $manifest['wall'],
            'users' => count($manifest['users']),
        ])->header('Cache-Control', 'no-store');
    }
}
