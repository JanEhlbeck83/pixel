<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    /** Bewusste Sprachwahl: speichert die Praeferenz im funktionalen Cookie und leitet auf die gleiche Seite um. */
    public function switch(Request $request, string $lang): RedirectResponse
    {
        abort_unless(Locales::isSupported($lang), 404);

        $to = (string) $request->query('to', '/');
        $safe = str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')
            && ! preg_match('/[\x00-\x1F\x7F]/', $to);
        $path = $safe ? parse_url($to, PHP_URL_PATH) : null;

        $request->session()->put('locale', $lang);

        return redirect(Locales::url($lang, $path ?: '/'))->withCookie(Cookie::make(
            config('locales.cookie'),
            $lang,
            config('locales.cookie_minutes'),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }
}
