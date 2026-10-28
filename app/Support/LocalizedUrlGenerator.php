<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;

/**
 * Seitenrouten sind zweimal registriert: "wall" (Englisch, ohne Prefix) und "localized.wall"
 * (/de/wall, /es/wall). route('wall') liefert automatisch die Variante der aktuellen Sprache.
 */
class LocalizedUrlGenerator extends UrlGenerator
{
    public const PREFIXED_NAME = 'localized.';

    public function route($name, $parameters = [], $absolute = true)
    {
        $locale = app()->getLocale();

        if ($locale !== config('locales.default')
            && ! str_starts_with($name, self::PREFIXED_NAME)
            && $this->routes->hasNamedRoute(self::PREFIXED_NAME.$name)
            && ! array_key_exists('locale', Arr::wrap($parameters))) {
            $name = self::PREFIXED_NAME.$name;
            $parameters = array_merge(Arr::wrap($parameters), ['locale' => $locale]);
        }

        return parent::route($name, $parameters, $absolute);
    }
}
