<?php

namespace App\Providers;

use App\Support\LocalizedUrlGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            return new LocalizedUrlGenerator(
                $routes,
                $app->rebinding('request', fn ($app, $request) => $app['url']->setRequest($request)),
                $app['config']['app.asset_url'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Uebersetzungen liegen je Bereich in lang/i18n/<bereich>/{en,es}.json (Schluessel = deutscher Text).
        foreach (glob(lang_path('i18n/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $this->app['translator']->addJsonPath($directory);
        }
    }
}
