<x-guest-layout>
    @if (session('status') == 'verification-link-sent')
        <div class="auth-status">
            {{ __('Ein neuer Bestätigungslink wurde an die bei der Registrierung angegebene E-Mail-Adresse gesendet.') }}
        </div>
    @endif

    <p class="auth-form-footer">
        {{ __('Danke für deine Registrierung! Bevor es losgeht: Bitte bestätige deine E-Mail-Adresse über den Link, den wir dir gerade geschickt haben. Falls du keine E-Mail erhalten hast, senden wir dir gern eine neue.') }}
    </p>

    <form class="auth-form" method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-primary-button class="auth-submit">
            <span>{{ __('Bestätigungs-E-Mail erneut senden') }}</span>
        </x-primary-button>
    </form>

    <form class="auth-form" method="POST" action="{{ route('logout') }}">
        @csrf
        <p class="auth-form-footer">
            <button type="submit" class="auth-inline-link">{{ __('Abmelden') }}</button>
        </p>
    </form>
</x-guest-layout>
