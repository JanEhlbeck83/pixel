<x-guest-layout>
    <x-auth-session-status class="auth-status" :status="session('status')" />

    <form class="auth-form" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="auth-field">
            <x-input-label for="email" value="{{ __('E-Mail-Adresse') }}" />
            <x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="{{ __('du@beispiel.de') }}" />
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <div class="auth-field">
            <div class="auth-label-row">
                <x-input-label for="password" value="{{ __('Passwort') }}" />
                @if (Route::has('password.request'))
                    <a class="auth-inline-link" href="{{ route('password.request') }}">{{ __('Passwort vergessen?') }}</a>
                @endif
            </div>
            <x-text-input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" placeholder="{{ __('Dein Passwort') }}" />
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <div class="auth-form-options">
            <label class="auth-checkbox" for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Angemeldet bleiben') }}</span>
            </label>
        </div>

        <x-primary-button class="auth-submit">
            <span>{{ __('Anmelden') }}</span>
            <span aria-hidden="true">→</span>
        </x-primary-button>
    </form>

    @include('auth.partials.social-buttons')

    <p class="auth-form-footer">{{ __('Noch nicht dabei?') }} <a href="{{ route('register') }}">{{ __('Kostenlos registrieren') }} <span aria-hidden="true">↗</span></a></p>
</x-guest-layout>
