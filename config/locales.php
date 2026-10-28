<?php

/*
|--------------------------------------------------------------------------
| Sprachen / Locales
|--------------------------------------------------------------------------
|
| Englisch ist die Standardsprache und liegt ohne Prefix auf "/".
| Deutsch liegt unter "/de", Spanisch unter "/es".
|
| Quelltexte im Code sind deutsch. Uebersetzungen stehen als JSON in
| lang/i18n/<bereich>/{en,es}.json (Schluessel = deutscher Originaltext).
|
*/

return [
    'default' => 'en',

    'supported' => ['en', 'de', 'es'],

    'labels' => [
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
    ],

    // Nur durch bewusste Auswahl des Nutzers gesetzt (funktionales Cookie, 1 Jahr).
    'cookie' => 'pixel_locale',
    'cookie_minutes' => 60 * 24 * 365,

    // Routen, deren URL keinen Sprach-Prefix traegt, obwohl sie in der Sprachgruppe liegen
    // (z. B. feste OAuth-Callback-URL). Die Sprache kommt dann aus der Session.
    'session_routes' => ['social.callback'],

    /*
    | Lokale GeoIP-Datenbank (DB-IP "IP to Country Lite", CC BY 4.0, https://db-ip.com).
    | Es werden KEINE IP-Adressen an Dritte uebertragen und nicht gespeichert.
    | Download/Aktualisierung: php artisan geoip:update
    */
    'geoip_database' => env('GEOIP_DATABASE', storage_path('app/geoip/dbip-country-lite.mmdb')),

    // Land (ISO 3166-1 alpha-2) => Sprache. Alles andere: Accept-Language, sonst Englisch.
    'country_locale' => [
        'DE' => 'de', 'AT' => 'de', 'CH' => 'de', 'LI' => 'de',
        'ES' => 'es', 'MX' => 'es', 'AR' => 'es', 'CO' => 'es', 'CL' => 'es', 'PE' => 'es',
        'VE' => 'es', 'EC' => 'es', 'GT' => 'es', 'CU' => 'es', 'BO' => 'es', 'DO' => 'es',
        'HN' => 'es', 'PY' => 'es', 'SV' => 'es', 'NI' => 'es', 'CR' => 'es', 'PA' => 'es',
        'UY' => 'es', 'PR' => 'es', 'GQ' => 'es',
    ],

    'bot_pattern' => '/bot|crawl|spider|slurp|facebookexternalhit|facebot|whatsapp|telegram|twitterbot|linkedin|preview|embed|curl|wget|lighthouse|headless/i',
];
