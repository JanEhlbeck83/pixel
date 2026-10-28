<?php

namespace Tests\Feature;

use App\Services\GeoLocale;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';

    public function test_english_is_served_without_prefix_and_other_languages_with_prefix(): void
    {
        $this->withHeader('User-Agent', self::BROWSER)->get('/')->assertOk()->assertHeader('Content-Language', 'en');
        $this->withHeader('User-Agent', self::BROWSER)->get('/de')->assertOk()->assertHeader('Content-Language', 'de');
        $this->withHeader('User-Agent', self::BROWSER)->get('/es')->assertOk()->assertHeader('Content-Language', 'es');
        $this->get('/fr')->assertNotFound();
    }

    public function test_pages_exist_in_every_language(): void
    {
        foreach (['', '/de', '/es'] as $prefix) {
            foreach (['/wall', '/gallery', '/imprint', '/privacy', '/terms', '/data-deletion', '/login', '/register', '/forgot-password'] as $path) {
                $this->get($prefix.$path)->assertOk();
            }
        }
    }

    public function test_old_german_legal_urls_redirect_permanently(): void
    {
        $this->get('/datenschutz')->assertStatus(301)->assertRedirect(url('/privacy'));
        $this->get('/de/impressum')->assertStatus(301)->assertRedirect(url('/de/imprint'));
    }

    public function test_urls_keep_the_current_language(): void
    {
        $html = $this->get('/de/login')->assertOk()->getContent();
        $this->assertStringContainsString('/de/register', $html);
        $this->assertStringContainsString('/de/privacy', $html);

        $html = $this->get('/login')->getContent();
        $this->assertStringContainsString(url('/register'), $html);
        $this->assertStringNotContainsString('/de/register', $html);
    }

    public function test_hreflang_alternates_are_rendered(): void
    {
        $html = $this->get('/de/privacy')->getContent();
        $this->assertStringContainsString('hreflang="en" href="'.url('/privacy').'"', $html);
        $this->assertStringContainsString('hreflang="de" href="'.url('/de/privacy').'"', $html);
        $this->assertStringContainsString('hreflang="es" href="'.url('/es/privacy').'"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
    }

    public function test_geoip_maps_countries_to_languages(): void
    {
        if (! is_file(config('locales.geoip_database'))) {
            $this->markTestSkipped('GeoIP-Datenbank fehlt (php artisan geoip:update).');
        }

        $geo = app(GeoLocale::class);
        $this->assertSame('DE', $geo->country('85.214.132.117'));   // Strato, Berlin
        $this->assertSame('ES', $geo->country('81.46.0.1'));       // Telefonica, Spanien
        $this->assertSame('US', $geo->country('8.8.8.8'));
        $this->assertNull($geo->country('127.0.0.1'));
    }

    public function test_root_redirects_first_time_visitors_by_country(): void
    {
        if (! is_file(config('locales.geoip_database'))) {
            $this->markTestSkipped('GeoIP-Datenbank fehlt.');
        }

        $german = ['REMOTE_ADDR' => '85.214.132.117', 'HTTP_USER_AGENT' => self::BROWSER];
        $spanish = ['REMOTE_ADDR' => '81.46.0.1', 'HTTP_USER_AGENT' => self::BROWSER];
        $american = ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_USER_AGENT' => self::BROWSER];

        $this->call('GET', '/', [], [], [], $german)->assertRedirect(url('/de'));
        $this->call('GET', '/', [], [], [], $spanish)->assertRedirect(url('/es'));
        $this->call('GET', '/', [], [], [], $american)->assertOk();
    }

    public function test_no_redirect_for_bots_or_other_paths_or_explicit_choice(): void
    {
        $german = ['REMOTE_ADDR' => '85.214.132.117'];

        $this->call('GET', '/', [], [], [], $german + ['HTTP_USER_AGENT' => 'facebookexternalhit/1.1'])->assertOk();
        $this->call('GET', '/', [], [], [], $german + ['HTTP_USER_AGENT' => 'Googlebot/2.1'])->assertOk();
        $this->call('GET', '/privacy', [], [], [], $german + ['HTTP_USER_AGENT' => self::BROWSER])->assertOk();
    }

    public function test_explicit_language_cookie_disables_redirect(): void
    {
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
            ->withCookie(config('locales.cookie'), 'en')
            ->withServerVariables(['REMOTE_ADDR' => '85.214.132.117', 'HTTP_USER_AGENT' => self::BROWSER])
            ->get('/')
            ->assertOk();
    }

    public function test_accept_language_is_used_when_country_is_unknown(): void
    {
        $server = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => self::BROWSER, 'HTTP_ACCEPT_LANGUAGE' => 'es-ES,es;q=0.9'];
        $this->call('GET', '/', [], [], [], $server)->assertRedirect(url('/es'));
    }

    public function test_switching_language_sets_cookie_and_keeps_page(): void
    {
        $response = $this->get('/language/de?to=/privacy');
        $response->assertRedirect(url('/de/privacy'))->assertCookie(config('locales.cookie'), 'de');

        $this->get('/language/en?to=/de/privacy')->assertRedirect(url('/privacy'));
        $this->get('/language/es?to=//evil.example')->assertRedirect(url('/es'));
        $this->get('/language/es?to=https://evil.example')->assertRedirect(url('/es'));
        $this->get('/language/fr')->assertNotFound();
    }

    public function test_api_uses_locale_header_and_unprefixed_urls(): void
    {
        $this->withHeader('X-App-Locale', 'es')->get('/api/activity')->assertOk()->assertHeader('Content-Language', 'es');
        $this->flushSession();
        $this->withHeader('X-App-Locale', 'en')->get('/api/activity')->assertHeader('Content-Language', 'en');
    }

    public function test_locales_helper_strips_and_builds_urls(): void
    {
        $this->assertSame('/', Locales::stripPrefix('/de'));
        $this->assertSame('/privacy', Locales::stripPrefix('/es/privacy'));
        $this->assertSame('/design', Locales::stripPrefix('/design'));
        $this->assertSame(url('/de/privacy'), Locales::url('de', '/privacy'));
        $this->assertSame(url('/'), Locales::url('en', '/de'));
    }
}
