<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Callbacks, die Facebook beim Entfernen der App bzw. bei einer Datenlöschung aufruft. */
class FacebookDataController extends Controller
{
    public function deauthorize(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        if (! $payload) {
            return response()->json(['error' => 'invalid_signed_request'], 400);
        }

        // Konto bleibt bestehen; nur die Facebook-Verknüpfung wird gelöst (Login weiter per Passwort-Reset möglich).
        User::where('provider', 'facebook')->where('provider_id', (string) $payload['user_id'])
            ->update(['provider' => null, 'provider_id' => null]);

        return response()->json(['status' => 'ok']);
    }

    public function dataDeletion(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        if (! $payload) {
            return response()->json(['error' => 'invalid_signed_request'], 400);
        }

        $code = Str::random(32);

        DB::transaction(function () use ($payload, $code) {
            $user = User::where('provider', 'facebook')->where('provider_id', (string) $payload['user_id'])->first();

            if ($user) {
                // Der Name steht im Klartext in der Beschreibung und muss mit dem Konto verschwinden.
                Activity::where('user_id', $user->id)->update(['type' => 'user_deleted', 'params' => null, 'description' => 'Ein gelöschter Nutzer hat Pixel gesetzt.']);
                $user->delete();
            }

            DB::table('data_deletion_requests')->insert(['code' => $code, 'provider' => 'facebook', 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json([
            'url' => route('legal.data-deletion.status', ['code' => $code]),
            'confirmation_code' => $code,
        ]);
    }

    private function payload(Request $request): ?array
    {
        $secret = config('services.facebook.client_secret');
        $signed = (string) $request->input('signed_request');

        if (! $secret || ! str_contains($signed, '.')) {
            return null;
        }

        [$encodedSig, $encodedPayload] = explode('.', $signed, 2);
        $sig = base64_decode(strtr($encodedSig, '-_', '+/'), true);
        $payload = json_decode((string) base64_decode(strtr($encodedPayload, '-_', '+/'), true), true);

        if ($sig === false || ! is_array($payload) || ($payload['algorithm'] ?? null) !== 'HMAC-SHA256' || empty($payload['user_id'])) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $encodedPayload, $secret, true), $sig)) {
            return null;
        }

        return $payload;
    }
}
