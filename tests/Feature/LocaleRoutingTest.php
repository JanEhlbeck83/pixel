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
            foreach (['/gallery', '/imprint', '/faq', '/privacy', '/terms', '/data-deletion', '/login', '/register', '/forgot-password'] as $path) {
                $this->get($prefix.$path)->assertOk();
            }
        }
    }

    public function test_shop_displays_small_business_vat_notice_and_withdrawal_consent(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Selection');

        $this->get('/de')
            ->assertOk()
            ->assertSee('Kleinunternehmer im Sinne von § 19 UStG')
            ->assertSee('Auswahl')
            ->assertDontSee('Bezahlen mit')
            ->assertSee('Ich habe die Widerrufsbelehrung gelesen')
            ->assertSee('Widerrufsbelehrung lesen');

        $this->get('/es')
            ->assertOk()
            ->assertSee('Selección');

        $this->get('/de/terms')
            ->assertOk()
            ->assertSee('keine Umsatzsteuer berechnet oder ausgewiesen');
    }

    public function test_pixel_mode_is_locked_by_default_and_localized(): void
    {
        foreach ([
            '/' => ['Start pixel mode', "Don't show this notice again"],
            '/de' => ['Pixelmodus starten', 'Diesen Hinweis dauerhaft ausblenden'],
            '/es' => ['Iniciar modo píxel', 'No volver a mostrar este aviso'],
        ] as $path => [$toggle, $dismiss]) {
            $this->get($path)
                ->assertOk()
                ->assertSee('aria-pressed="false"', false)
                ->assertSee($toggle)
                ->assertSee('id="pixelModeModal"', false)
                ->assertSee($dismiss);
        }
    }

    public function test_old_german_legal_urls_redirect_permanently(): void
    {
        $this->get('/datenschutz')->assertStatus(301)->assertRedirect(url('/privacy'));
        $this->get('/de/impressum')->assertStatus(301)->assertRedirect(url('/de/imprint'));
        $this->get('/de/wall')->assertStatus(301)->assertRedirect(url('/de'));
    }

    public function test_urls_keep_the_current_language(): void
    {
        $html = $this->get('/de/login')->assertOk()->getContent();
        $this->assertStringContainsString('/de/register', $html);
        $this->assertStringContainsString('/de/imprint', $html);

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

    public function test_pixelart_label_is_used_in_every_language_without_changing_gallery_urls(): void
    {
        foreach (['', '/de', '/es'] as $prefix) {
            $this->get($prefix.'/gallery')
                ->assertOk()
                ->assertSee('Pixelart')
                ->assertDontSee('Galerie')
                ->assertDontSee('Gallery')
                ->assertDontSee('Galería');

            $this->get($prefix.'/privacy')->assertOk()->assertSee('Pixelart');
            $this->get($prefix.'/terms')->assertOk()->assertSee('Pixelart');
        }

    }

    public function test_page_brand_and_shared_menu_always_link_back_to_the_wall(): void
    {
        foreach (['/gallery' => '/', '/community' => '/', '/de/gallery' => '/de'] as $path => $wall) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="brand-wordmark" href="'.url($wall).'"', $html);
            $this->assertStringContainsString('aria-label="PixRiot –', $html);
        }
    }

    public function test_community_pages_use_the_current_url_locale(): void
    {
        $this->get('/community')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('href="'.url('/community').'"', false);

        $this->get('/de/community')
            ->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertSee('href="'.url('/de/community').'"', false)
            ->assertSee('aria-label="PixRiot – zur Wand"', false);

        $this->get('/es/community')
            ->assertOk()
            ->assertHeader('Content-Language', 'es')
            ->assertSee('href="'.url('/es/community').'"', false);
    }

    public function test_home_and_pixelart_seo_texts_are_localized(): void
    {
        $texts = [
            'en' => [
                'home_title' => 'PixRiot – Create Pixelart together online',
                'home_description' => 'Join a PixRiot group, vote on pixel-art ideas, create together, and publish your work on the community wall.',
                'pixelart_title' => 'Pixelart – Community artworks & snapshots | PixRiot',
                'pixelart_description' => 'Discover artworks created by the PixRiot community. Regular snapshots show how our shared pixel wall changes over time.',
                'welcome_heading' => 'Your pixels. Your group. One shared creation.',
                'heading' => 'Artworks in motion',
                'intro' => 'Snapshots of our shared pixel wall.',
                'start' => 'Start playing',
            ],
            'de' => [
                'home_title' => 'PixRiot – Gemeinsam Pixelart online gestalten',
                'home_description' => 'Tritt bei PixRiot einer Gruppe bei, stimmt über Pixelart-Ideen ab, gestaltet gemeinsam und veröffentlicht euer Werk auf der Community-Leinwand.',
                'pixelart_title' => 'Pixelart – Community-Kunstwerke & Snapshots | PixRiot',
                'pixelart_description' => 'Entdecke die Kunstwerke der PixRiot-Community. Regelmäßige Snapshots zeigen, wie sich unsere gemeinsame Pixelwand verändert.',
                'welcome_heading' => 'Deine Pixel. Eure Gruppe. Ein gemeinsames Werk.',
                'heading' => 'Kunstwerke im Wandel',
                'intro' => 'Snapshots unserer gemeinsamen Pixelwand.',
                'start' => 'Spiel starten',
            ],
            'es' => [
                'home_title' => 'PixRiot – Crea Pixelart en comunidad online',
                'home_description' => 'Únete a un grupo de PixRiot, votad ideas de pixel art, cread juntos y publicad vuestra obra en el muro comunitario.',
                'pixelart_title' => 'Pixelart – Obras de la comunidad y capturas | PixRiot',
                'pixelart_description' => 'Descubre las obras de la comunidad de PixRiot. Las capturas periódicas muestran cómo cambia nuestro muro de píxeles compartido.',
                'welcome_heading' => 'Tus píxeles. Tu grupo. Una creación compartida.',
                'heading' => 'Obras de arte en constante cambio',
                'intro' => 'Capturas de nuestro muro de píxeles compartido.',
                'start' => 'Empezar a jugar',
            ],
        ];

        foreach ($texts as $locale => $text) {
            $home = $this->withHeader('User-Agent', 'Googlebot')
                ->get(Locales::url($locale))->assertOk()->getContent();
            $pixelart = $this->get(Locales::url($locale, '/gallery'))->assertOk()->getContent();

            $this->assertStringContainsString('<title>'.e($text['home_title']).'</title>', $home);
            $this->assertStringContainsString('<meta name="description" content="'.e($text['home_description']).'">', $home);
            $this->assertStringContainsString('<title>'.e($text['pixelart_title']).'</title>', $pixelart);
            $this->assertStringContainsString('<meta name="description" content="'.e($text['pixelart_description']).'">', $pixelart);
            $this->assertStringContainsString('<h1>'.e($text['heading']).'</h1>', $pixelart);
            $this->assertStringContainsString('<p>'.e($text['intro']).'</p>', $pixelart);
            $this->assertSame(1, substr_count($home, '<meta name="description"'));
            $this->assertSame(1, substr_count($pixelart, '<meta name="description"'));
            $this->assertSame(1, substr_count($pixelart, '<h1>'));
            $this->assertStringContainsString('<h1 id="welcomeTitle">'.e($text['welcome_heading']).'</h1>', $home);
            $this->assertStringContainsString('<p id="welcomeDescription">'.e($text['home_description']).'</p>', $home);
            $this->assertStringContainsString('id="startGameBtn" class="primary-button">'.e($text['start']).'</button>', $home);
            $this->assertStringContainsString('id="welcomeOverlay" class="welcome-overlay visible"', $home);
            $this->assertSame(1, substr_count($home, '<h1 '));
        }
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
