<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = [];

        foreach (['/', '/gallery', '/faq', '/imprint', '/privacy', '/terms', '/data-deletion'] as $path) {
            $alternates = [];
            foreach (Locales::supported() as $locale) {
                $alternates[$locale] = Locales::url($locale, $path);
            }

            $pages[] = [
                'urls' => $alternates,
                'alternates' => $alternates + ['x-default' => $alternates[Locales::default()]],
            ];
        }

        return response()->view('sitemap', compact('pages'), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
