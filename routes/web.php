<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WallController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WallController::class, 'index']);

Route::get('/wall', [WallController::class, 'index'])->name('wall');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
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
    Route::get('/dashboard', function () {
        return redirect()->route('wall');
    })->name('dashboard');

    Route::post('/api/pixels/lock', [WallController::class, 'lockPixelArea'])->name('api.pixels.lock');
    Route::delete('/api/pixels/lock', [WallController::class, 'releasePixelAreaLock'])->name('api.pixels.lock.release');
});

Route::middleware('auth')->group(function () {
    Route::post('/shop/checkout', [ShopController::class, 'checkout'])->middleware('throttle:20,1')->name('shop.checkout');
    Route::get('/shop/return/{order}', [ShopController::class, 'return'])->name('shop.return');
    Route::get('/shop/cancel/{order}', [ShopController::class, 'cancel'])->name('shop.cancel');
    Route::get('/shop/fake/{order}', [ShopController::class, 'fake'])->name('shop.fake');
    Route::post('/shop/fake/{order}', [ShopController::class, 'fakePay'])->name('shop.fake.pay');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
