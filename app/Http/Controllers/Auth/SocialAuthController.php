<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        if (! config("services.$provider.client_id") || ! config("services.$provider.client_secret")) {
            return redirect()->route('login')->withErrors([
                'email' => ucfirst($provider).'-Login ist noch nicht eingerichtet (Zugangsdaten in .env fehlen).',
            ]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Die Anmeldung über '.ucfirst($provider).' ist fehlgeschlagen.']);
        }

        $user = User::where('provider', $provider)->where('provider_id', $social->getId())->first();

        if (! $user) {
            $email = $social->getEmail();
            if (! $email) {
                return redirect()->route('login')->withErrors(['email' => ucfirst($provider).' hat keine E-Mail-Adresse übermittelt. Bitte registriere dich per E-Mail.']);
            }

            $existing = User::where('email', $email)->first();
            if ($existing && $existing->provider === null) {
                // Bestehendes Konto nicht automatisch übernehmen (Account-Takeover-Schutz).
                return redirect()->route('login')->withErrors(['email' => 'Diese E-Mail ist bereits registriert. Bitte melde dich mit Passwort an.']);
            }

            // Erstmaliger Login = automatische Registrierung.
            $user = User::create([
                'name' => Str::limit($social->getName() ?: Str::before($email, '@'), 100, ''),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->forceFill([
                'provider' => $provider,
                'provider_id' => $social->getId(),
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended('/');
    }
}
