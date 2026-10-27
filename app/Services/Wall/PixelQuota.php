<?php

namespace App\Services\Wall;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Wer darf wie viele Pixel setzen?
 *  - Gast: einmaliger Vorrat (Standard 50) pro IP, danach nur mit Konto.
 *  - Konto: Vorrat von höchstens 50 kostenlosen Pixeln, der alle 30 Sekunden um 1 nachwächst.
 *    Neue Konten starten mit vollem Vorrat. Darüber hinaus gibt es nur gekaufte Extra-Pixel.
 */
class PixelQuota
{
    public function guestKey(Request $request): string
    {
        return 'guest-pixels-used:'.hash('sha256', $request->ip() ?? 'unknown');
    }

    public function guestLimit(): int
    {
        return (int) config('payments.limits.guest_pixels');
    }

    public function guestUsed(Request $request): int
    {
        return (int) Cache::get($this->guestKey($request), 0);
    }

    public function recordGuestPixel(Request $request): void
    {
        Cache::forever($this->guestKey($request), $this->guestUsed($request) + 1);
    }

    public function maxFree(): int
    {
        return (int) config('payments.limits.user_max_free_pixels');
    }

    public function regenSeconds(): int
    {
        return max(1, (int) config('payments.limits.user_regen_seconds'));
    }

    /** @return array{authenticated:bool,can_edit:bool,limit:?string,retry_after:int,credits:int,free:int,max_free:int} */
    public function status(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            $remaining = max(0, $this->guestLimit() - $this->guestUsed($request));

            return [
                'authenticated' => false,
                'can_edit' => $remaining > 0,
                'limit' => $remaining > 0 ? null : 'guest',
                'retry_after' => 0,
                'credits' => 0,
                'free' => $remaining,
                'max_free' => $this->guestLimit(),
            ];
        }

        [$free] = $this->bucket($user);
        $canEdit = $free > 0 || $user->pixel_credits > 0;

        return [
            'authenticated' => true,
            'can_edit' => $canEdit,
            'limit' => $canEdit ? null : 'user',
            'retry_after' => $canEdit ? 0 : $this->secondsUntilNext($user),
            'credits' => (int) $user->pixel_credits,
            'free' => $free,
            'max_free' => $this->maxFree(),
        ];
    }

    /** Sekunden bis das nächste kostenlose Pixel nachgewachsen ist (0 bei vollem Vorrat). */
    public function secondsUntilNext(User $user): int
    {
        [$free, $anchor] = $this->bucket($user);
        if ($free >= $this->maxFree()) {
            return 0;
        }

        return max(1, (int) ceil(now()->floatDiffInSeconds($anchor->copy()->addSeconds($this->regenSeconds()), false)));
    }

    /**
     * Verbraucht eine Pixel-Berechtigung. Muss in einer Transaktion laufen.
     * Nutzt zuerst den kostenlosen Vorrat, danach gekaufte Extra-Pixel.
     *
     * @return array<string,mixed>|null null = erlaubt, sonst die Ablehnung als Status-Array
     */
    public function consume(User $user): ?array
    {
        $locked = User::query()->lockForUpdate()->findOrFail($user->id);
        [$free, $anchor] = $this->bucket($locked);

        if ($free > 0) {
            // Läuft der Vorrat gerade aus dem vollen Zustand, startet der Nachwachs-Takt erst jetzt.
            $locked->forceFill([
                'free_pixels' => $free - 1,
                'free_pixels_at' => $free >= $this->maxFree() ? now() : $anchor,
            ])->save();

            return null;
        }

        if ($locked->pixel_credits > 0) {
            $locked->forceFill(['free_pixels' => 0, 'free_pixels_at' => $anchor])->save();
            $locked->decrement('pixel_credits');

            return null;
        }

        return [
            'authenticated' => true,
            'can_edit' => false,
            'limit' => 'user',
            'retry_after' => $this->secondsUntilNext($locked),
            'credits' => 0,
            'free' => 0,
            'max_free' => $this->maxFree(),
        ];
    }

    /**
     * Aktueller Vorrat inkl. Nachwachsen und der Zeitpunkt, ab dem das nächste Pixel zählt.
     *
     * @return array{0:int,1:\Illuminate\Support\Carbon}
     */
    private function bucket(User $user): array
    {
        $max = $this->maxFree();
        $stored = min((int) ($user->free_pixels ?? $max), $max);
        $anchor = $user->free_pixels_at ?? now();

        if ($stored >= $max) {
            return [$max, now()];
        }

        $elapsed = max(0, (int) floor($anchor->diffInSeconds(now(), false)));
        $gained = intdiv($elapsed, $this->regenSeconds());
        $free = min($max, $stored + $gained);

        return [$free, $free >= $max ? now() : $anchor->copy()->addSeconds($gained * $this->regenSeconds())];
    }
}
