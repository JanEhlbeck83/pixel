<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WallController;
use App\Http\Middleware\GeoLocaleRedirect;
use Illuminate\Support\Facades\Route;

// Bewusste Sprachwahl (setzt das funktionale Sprach-Cookie).
Route::get('/language/{lang}', [LanguageController::class, 'switch'])->name('language.switch');

// Seiten in allen Sprachen: "/" = Englisch, "/de/..." = Deutsch, "/es/..." = Spanisch.
foreach ([false, true] as $prefixed) {
Route::group($prefixed ? ['prefix' => '{locale}', 'where' => ['locale' => 'de|es'], 'as' => 'localized.', 'locale_page' => true] : ['locale_page' => true], function () {
    Route::get('/', [WallController::class, 'index'])->middleware(GeoLocaleRedirect::class)->name('home');
    Route::get('/wall', [WallController::class, 'index'])->name('wall');
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');

    Route::get('/imprint', [LegalController::class, 'imprint'])->name('legal.impressum');
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
    Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
    Route::get('/data-deletion', [LegalController::class, 'dataDeletion'])->name('legal.data-deletion');

    // Alte deutsche Adressen bleiben gueltig (dauerhafte Weiterleitung).
    Route::get('/impressum', fn () => redirect()->route('legal.impressum', [], 301));
    Route::get('/datenschutz', fn () => redirect()->route('legal.privacy', [], 301));
    Route::get('/nutzungsbedingungen', fn () => redirect()->route('legal.terms', [], 301));
    Route::get('/datenloeschung', fn () => redirect()->route('legal.data-deletion', [], 301));

    Route::middleware(['auth', 'verified'])->get('/dashboard', function () {
        return redirect()->route('wall');
    })->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
}

// Technische Endpunkte ohne Sprach-Prefix (Sprache kommt aus Header X-App-Locale bzw. Session).
Route::get('/api/pixels', [WallController::class, 'pixels'])->name('api.pixels');
Route::get('/api/pixels/locks', [WallController::class, 'pixelLocks'])->name('api.pixels.locks');
Route::post('/api/pixels', [WallController::class, 'store'])->name('api.pixels.store');
Route::get('/api/activity', [WallController::class, 'activity'])->name('api.activity');
Route::get('/api/background-image/version', [WallController::class, 'backgroundImageVersion'])->name('api.background.version');
Route::get('/api/wall', [WallController::class, 'dimensions'])->name('api.wall.dimensions');
Route::get('/api/wall/tile', [WallController::class, 'tile'])->name('api.wall.tile');
Route::get('/api/pixels/status', [WallController::class, 'pixelStatus'])->name('api.pixels.status');
Route::get('/api/shop', [ShopController::class, 'catalog'])->name('shop.catalog');
Route::post('/webhooks/stripe', [ShopController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/webhooks/mollie', [ShopController::class, 'mollieWebhook'])->name('webhooks.mollie');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:3,1')->name('feedback.store');

Route::post('/api/background-image', [WallController::class, 'backgroundImage'])->name('api.background.image');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/api/pixels/lock', [WallController::class, 'lockPixelArea'])->name('api.pixels.lock');
    Route::delete('/api/pixels/lock', [WallController::class, 'releasePixelAreaLock'])->name('api.pixels.lock.release');
});

Route::middleware('auth')->group(function () {
    Route::post('/shop/checkout', [ShopController::class, 'checkout'])->middleware('throttle:20,1')->name('shop.checkout');
    Route::get('/shop/return/{order}', [ShopController::class, 'return'])->name('shop.return');
    Route::get('/shop/cancel/{order}', [ShopController::class, 'cancel'])->name('shop.cancel');
    Route::get('/shop/fake/{order}', [ShopController::class, 'fake'])->name('shop.fake');
    Route::post('/shop/fake/{order}', [ShopController::class, 'fakePay'])->name('shop.fake.pay');
});
