<?php

namespace Tests\Feature;

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_only_public_canonical_pages_and_all_language_alternates(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept-Language' => 'de-DE',
        ])->get('/sitemap.xml?tracking=ignored')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $expected = [];
        foreach (['/', '/gallery', '/faq', '/imprint', '/privacy', '/terms', '/data-deletion'] as $path) {
            $alternates = [];
            foreach (Locales::supported() as $locale) {
                $alternates[$locale] = Locales::url($locale, $path);
            }
            $alternates['x-default'] = $alternates[Locales::default()];

            foreach (Locales::supported() as $locale) {
                $url = Locales::url($locale, $path);
                $expected[] = $url;
                $entries = $xml->xpath('/s:urlset/s:url[s:loc="'.$url.'"]');
                $this->assertCount(1, $entries);

                $actualAlternates = [];
                foreach ($entries[0]->children('http://www.w3.org/1999/xhtml')->link as $link) {
                    $attributes = $link->attributes();
                    $this->assertSame('alternate', (string) $attributes['rel']);
                    $actualAlternates[(string) $attributes['hreflang']] = (string) $attributes['href'];
                }
                $this->assertSame($alternates, $actualAlternates);
            }
        }

        $actual = array_map(fn ($loc) => (string) $loc, $xml->xpath('/s:urlset/s:url/s:loc'));
        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertCount(21, $actual);
        $this->assertStringNotContainsString('tracking', $response->getContent());
    }

    public function test_all_sitemap_pages_are_crawlable_and_self_canonical(): void
    {
        foreach (Locales::supported() as $locale) {
            foreach (['/', '/gallery', '/faq', '/imprint', '/privacy', '/terms', '/data-deletion'] as $path) {
                $url = Locales::url($locale, $path);
                $this->withHeader('User-Agent', 'Googlebot')
                    ->get($url)
                    ->assertOk()
                    ->assertHeader('Content-Language', $locale)
                    ->assertSee('<link rel="canonical" href="'.$url.'">', false);
            }
        }
    }

    public function test_robots_file_advertises_the_live_sitemap(): void
    {
        $this->assertStringContainsString(
            'Sitemap: https://pixriot.online/sitemap.xml',
            file_get_contents(public_path('robots.txt')),
        );
    }
}
