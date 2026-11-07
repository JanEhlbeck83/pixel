<?php

namespace App\Services\Wall;

use App\Models\WallDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WallDrafts
{
    public const COOKIE = 'wall_draft';

    public function guestHash(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)
            ? hash('sha256', $token) : null;
    }

    public function claim(Request $request): void
    {
        $hash = $this->guestHash($request);
        if ($hash === null || $request->user() === null) {
            return;
        }
        DB::transaction(function () use ($request, $hash) {
            WallDraft::where('guest_hash', $hash)->whereNull('user_id')
                ->where('expires_at', '>', now())->lockForUpdate()->get()->each(function ($draft) use ($request) {
                    $draft->update(['user_id' => $request->user()->id, 'guest_hash' => null, 'expires_at' => null]);
                });
        });
    }

    public function owned(Request $request, int $id): WallDraft
    {
        return WallDraft::query()->whereKey($id)->where(function ($query) use ($request) {
            if ($request->user()) {
                $query->where('user_id', $request->user()->id);
            } else {
                $hash = $this->guestHash($request);
                abort_if($hash === null, 404);
                $query->whereNull('user_id')->where('guest_hash', $hash)->where('expires_at', '>', now());
            }
        })->lockForUpdate()->firstOrFail();
    }

    public function payload(WallDraft $draft): array
    {
        return $draft->only(['id', 'title', 'pixels', 'paid_cells', 'version']);
    }
}
