<?php

use App\Http\Controllers\AdminNewsletterController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminCommunityGroupController;
use App\Http\Controllers\AnalyticsHeartbeatController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityProjectController;
use App\Http\Controllers\CommunityPhaseUpdateController;
use App\Http\Controllers\NewsletterPublicController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WallController;
use App\Http\Middleware\GeoLocaleRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/api/community/reservations', [CommunityController::class, 'reservations'])->name('api.community.reservations');
Route::get('/api/community/phase-updates', CommunityPhaseUpdateController::class)
    ->middleware(['auth', 'verified', 'throttle:30,1'])->name('api.community.phase-updates');

// Bewusste Sprachwahl (setzt das funktionale Sprach-Cookie).
Route::get('/language/{lang}', [LanguageController::class, 'switch'])->name('language.switch');

// Seiten in allen Sprachen: "/" = Englisch, "/de/..." = Deutsch, "/es/..." = Spanisch.
foreach ([false, true] as $prefixed) {
Route::group($prefixed ? ['prefix' => '{locale}', 'where' => ['locale' => 'de|es'], 'as' => 'localized.', 'locale_page' => true] : ['locale_page' => true], function () {
    Route::get('/', [WallController::class, 'index'])->middleware(GeoLocaleRedirect::class)->name('home');
    Route::get('/wall', fn () => redirect()->route('home', [], 301));
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
    Route::get('/artists/{user}/gallery', [\App\Http\Controllers\WallArtworkController::class, 'index'])->name('artworks.index');
    Route::get('/artworks/{artwork}/image', [\App\Http\Controllers\WallArtworkController::class, 'image'])->name('artworks.image');
    Route::patch('/artworks/{artwork}', [\App\Http\Controllers\WallArtworkController::class, 'update'])->middleware('auth')->name('artworks.update');
    Route::delete('/artworks/{artwork}', [\App\Http\Controllers\WallArtworkController::class, 'destroy'])->middleware('auth')->name('artworks.destroy');

    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::get('/community/groups/{group}/gallery', [CommunityController::class, 'gallery'])->name('community.groups.gallery');
    Route::post('/community/groups', [CommunityController::class, 'store'])->middleware(['auth', 'verified'])->name('community.groups.store');
    Route::get('/community/groups/{group}', [CommunityController::class, 'show'])->name('community.groups.show');
    Route::post('/community/groups/{group}/join', [CommunityController::class, 'join'])->middleware(['auth', 'verified'])->name('community.groups.join');
    Route::delete('/community/group-membership', [CommunityController::class, 'leave'])->middleware(['auth', 'verified'])->name('community.groups.leave');
    Route::post('/community/groups/{group}/projects', [CommunityController::class, 'createProject'])->middleware(['auth', 'verified'])->name('community.projects.store');
    Route::get('/community/projects/{project}', [CommunityProjectController::class, 'show'])->name('community.projects.show');
    Route::get('/community/projects/{project}/image', [CommunityProjectController::class, 'image'])->name('community.projects.image');
    Route::get('/community/projects/{project}/template', [CommunityProjectController::class, 'templateImage'])->middleware(['auth', 'verified'])->name('community.projects.template');
    Route::patch('/community/projects/{project}/template', [CommunityProjectController::class, 'updateTemplate'])->middleware(['auth', 'verified'])->name('community.projects.template.update');
    Route::post('/community/projects/{project}/join', [CommunityProjectController::class, 'join'])->middleware(['auth', 'verified'])->name('community.projects.join');
    Route::post('/community/projects/{project}/cancel', [CommunityProjectController::class, 'cancel'])->middleware(['auth', 'verified'])->name('community.projects.cancel');
    Route::post('/community/projects/{project}/reserve', [CommunityProjectController::class, 'reserve'])->middleware(['auth', 'verified'])->name('community.projects.reserve');
    Route::get('/community/projects/{project}/pixels', [CommunityProjectController::class, 'pixels'])->middleware(['auth', 'verified'])->name('community.projects.pixels');
    Route::post('/community/projects/{project}/pixels', [CommunityProjectController::class, 'paint'])->middleware(['auth', 'verified', 'throttle:600,1'])->name('community.projects.paint');
    Route::post('/community/projects/{project}/complete', [CommunityProjectController::class, 'complete'])->middleware(['auth', 'verified'])->name('community.projects.complete');
    Route::get('/community/projects/{project}/chat', [CommunityProjectController::class, 'chat'])->middleware(['auth', 'verified'])->name('community.projects.chat');
    Route::post('/community/projects/{project}/chat', [CommunityProjectController::class, 'sendChatMessage'])->middleware(['auth', 'verified', 'throttle:20,1'])->name('community.projects.chat.send');

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
        Route::patch('/profile/project-notifications', [ProfileController::class, 'updateProjectNotifications'])->name('profile.project-notifications.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
}

// Technische Endpunkte ohne Sprach-Prefix (Sprache kommt aus Header X-App-Locale bzw. Session).
Route::get('/api/soak/status', \App\Http\Controllers\SoakStatusController::class)
    ->middleware(['auth', 'verified'])->name('soak.status');
Route::get('/api/pixels', [WallController::class, 'pixels'])->name('api.pixels');
Route::get('/api/pixels/locks', [WallController::class, 'pixelLocks'])->name('api.pixels.locks');
Route::get('/api/pixels/recent', [WallController::class, 'recentPixels'])->name('api.pixels.recent');
Route::post('/api/pixels', [WallController::class, 'store'])->name('api.pixels.store');
Route::post('/api/pixels/glyph', \App\Http\Controllers\WallGlyphController::class)->middleware('throttle:30,1')->name('api.pixels.glyph');
Route::get('/api/wall-drafts', [\App\Http\Controllers\WallDraftController::class, 'index'])->middleware('throttle:60,1');
Route::patch('/api/wall-drafts/{draft}', [\App\Http\Controllers\WallDraftController::class, 'update'])->middleware('throttle:120,1')->whereNumber('draft');
Route::post('/api/wall-drafts/{draft}/publish', [\App\Http\Controllers\WallDraftController::class, 'publish'])->middleware('throttle:30,1')->whereNumber('draft');
Route::get('/api/activity', [WallController::class, 'activity'])->name('api.activity');
Route::get('/api/wall', [WallController::class, 'dimensions'])->name('api.wall.dimensions');
Route::get('/api/pixels/status', [WallController::class, 'pixelStatus'])->name('api.pixels.status');
Route::get('/api/shop', [ShopController::class, 'catalog'])->name('shop.catalog');
Route::post('/webhooks/stripe', [ShopController::class, 'stripeWebhook'])->name('webhooks.stripe');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:3,1')->name('feedback.store');
Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:10,60')->name('report.store');
Route::post('/analytics/heartbeat', AnalyticsHeartbeatController::class)->middleware('throttle:120,1')->name('analytics.heartbeat');
Route::get('/newsletter-images/{file}', [NewsletterPublicController::class, 'image'])->name('newsletter.image');
Route::match(['get', 'post'], '/newsletter/unsubscribe/{user}', [NewsletterPublicController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:20,1'])->name('newsletter.unsubscribe');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/community-groups', [AdminCommunityGroupController::class, 'index'])->name('admin.community-groups');
    Route::post('/community-groups/{group}/approve', [AdminCommunityGroupController::class, 'approve'])->name('admin.community-groups.approve');
    Route::post('/community-groups/{group}/reject', [AdminCommunityGroupController::class, 'reject'])->name('admin.community-groups.reject');
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics');
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
