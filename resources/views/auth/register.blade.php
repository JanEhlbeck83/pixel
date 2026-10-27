<x-guest-layout>
    <form class="auth-form" method="POST" action="{{ route('register') }}">
        @csrf

        <div class="auth-field">
            <x-input-label for="name" value="Dein Name" />
            <x-text-input id="name" class="auth-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Wie sollen wir dich nennen?" />
            <x-input-error :messages="$errors->get('name')" class="auth-error" />
        </div>

        <div class="auth-field">
            <x-input-label for="email" value="E-Mail-Adresse" />
            <x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="du@beispiel.de" />
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <div class="auth-field">
            <x-input-label for="password" value="Passwort" />
            <x-text-input id="password" class="auth-input" type="password" name="password" required autocomplete="new-password" placeholder="Mindestens 8 Zeichen" />
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <div class="auth-field">
            <x-input-label for="password_confirmation" value="Passwort bestätigen" />
            <x-text-input id="password_confirmation" class="auth-input" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Passwort erneut eingeben" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-error" />
        </div>

        <x-primary-button class="auth-submit">
            <span>Konto erstellen</span>
            <span aria-hidden="true">→</span>
        </x-primary-button>
    </form>

    @include('auth.partials.social-buttons')

    <p class="auth-form-footer">Schon registriert? <a href="{{ route('login') }}">Jetzt anmelden <span aria-hidden="true">↗</span></a></p>
</x-guest-layout>
