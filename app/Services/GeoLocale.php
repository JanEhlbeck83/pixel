<?php

namespace App\Services;

use App\Support\Locales;
use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Ermittelt die passende Sprache zu einer Anfrage: erst per IP-Land (lokale Datenbank, keine
 * Weitergabe der IP an Dritte, keine Speicherung), sonst per Accept-Language-Header.
 */
class GeoLocale
{
    public function detect(Request $request): string
    {
        $country = $this->country($request->ip());
        $mapped = $country ? (config('locales.country_locale')[$country] ?? null) : null;

        if ($mapped !== null) {
            return $mapped;
        }

        return $request->getPreferredLanguage(Locales::supported()) ?: Locales::default();
    }

    public function country(?string $ip): ?string
    {
        $file = config('locales.geoip_database');
        if (! $ip || ! is_file($file)) {
            return null;
        }

        try {
            $reader = new Reader($file);
            $record = $reader->get($ip);
            $reader->close();
        } catch (Throwable) {
            return null;
        }

        $code = is_array($record) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($code) ? strtoupper($code) : null;
    }
}
