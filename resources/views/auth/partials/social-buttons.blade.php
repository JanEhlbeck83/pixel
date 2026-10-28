<div class="social-login">
    <span class="social-login-divider">{{ __('oder mit') }}</span>
    <a class="social-button social-google" href="{{ route('social.redirect', 'google') }}">Google</a>
    <a class="social-button social-facebook" href="{{ route('social.redirect', 'facebook') }}">Facebook</a>
    <p class="social-login-hint">{{ __('Beim ersten Login wird automatisch ein Konto angelegt.') }}</p>
</div>
