<?php

use App\Http\Controllers\AdminNewsletterController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\NewsletterPublicController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ReportController;
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
    Route::get('/wall', fn () => redirect()->route('home', [], 301));
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');

    Route::get('/imprint', [LegalController::class, 'imprint'])->name('legal.impressum');
    Route::get('/faq', [LegalController::class, 'faq'])->name('faq');
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
    Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
    Route::get('/data-deletion', [LegalController::class, 'dataDeletion'])->name('legal.data-deletion');
    Route::get('/data-deletion/status/{code}', [LegalController::class, 'dataDeletionStatus'])->name('legal.data-deletion.status');

    // Alte deutsche Adressen bleiben gueltig (dauerhafte Weiterleitung).
    Route::get('/impressum', fn () => redirect()->route('legal.impressum', [], 301));
    Route::get('/datenschutz', fn () => redirect()->route('legal.privacy', [], 301));
    Route::get('/nutzungsbedingungen', fn () => redirect()->route('legal.terms', [], 301));
    Route::get('/datenloeschung', fn () => redirect()->route('legal.data-deletion', [], 301));

    Route::middleware(['auth', 'verified'])->get('/dashboard', function () {
        return redirect()->route('home');
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
Route::get('/api/pixels/recent', [WallController::class, 'recentPixels'])->name('api.pixels.recent');
Route::post('/api/pixels', [WallController::class, 'store'])->name('api.pixels.store');
Route::get('/api/activity', [WallController::class, 'activity'])->name('api.activity');
Route::get('/api/wall', [WallController::class, 'dimensions'])->name('api.wall.dimensions');
Route::get('/api/pixels/status', [WallController::class, 'pixelStatus'])->name('api.pixels.status');
Route::get('/api/shop', [ShopController::class, 'catalog'])->name('shop.catalog');
Route::post('/webhooks/stripe', [ShopController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:3,1')->name('feedback.store');
Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:10,60')->name('report.store');
Route::get('/newsletter-images/{file}', [NewsletterPublicController::class, 'image'])->name('newsletter.image');
Route::match(['get', 'post'], '/newsletter/unsubscribe/{user}', [NewsletterPublicController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:20,1'])->name('newsletter.unsubscribe');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports');
    Route::get('/reports/{report}/image', [AdminReportController::class, 'image'])->name('admin.reports.image');
    Route::post('/reports/{report}/reset', [AdminReportController::class, 'reset'])->name('admin.reports.reset');
    Route::post('/reports/{report}/reset-ban', [AdminReportController::class, 'resetAndBan'])->name('admin.reports.reset-ban');
    Route::post('/reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('admin.reports.dismiss');

    Route::get('/newsletters', [AdminNewsletterController::class, 'index'])->name('admin.newsletters');
    Route::get('/newsletters/create', [AdminNewsletterController::class, 'create'])->name('admin.newsletters.create');
    Route::post('/newsletters', [AdminNewsletterController::class, 'store'])->name('admin.newsletters.store');
    Route::get('/newsletters/{newsletter}/edit', [AdminNewsletterController::class, 'edit'])->name('admin.newsletters.edit');
    Route::put('/newsletters/{newsletter}', [AdminNewsletterController::class, 'update'])->name('admin.newsletters.update');
    Route::delete('/newsletters/{newsletter}', [AdminNewsletterController::class, 'destroy'])->name('admin.newsletters.destroy');
    Route::get('/newsletters/{newsletter}/preview', [AdminNewsletterController::class, 'preview'])->name('admin.newsletters.preview');
    Route::post('/newsletters/{newsletter}/test', [AdminNewsletterController::class, 'test'])->name('admin.newsletters.test');
    Route::post('/newsletters/{newsletter}/send', [AdminNewsletterController::class, 'send'])->name('admin.newsletters.send');
});

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
