<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WallController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('wall');
});

Route::get('/wall', [WallController::class, 'index'])->name('wall');
Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
Route::get('/api/pixels', [WallController::class, 'pixels'])->name('api.pixels');
Route::get('/api/pixels/locks', [WallController::class, 'pixelLocks'])->name('api.pixels.locks');
Route::get('/api/activity', [WallController::class, 'activity'])->name('api.activity');
Route::get('/api/background-image/version', [WallController::class, 'backgroundImageVersion'])->name('api.background.version');
Route::get('/api/wall', [WallController::class, 'dimensions'])->name('api.wall.dimensions');
Route::get('/api/wall/tile', [WallController::class, 'tile'])->name('api.wall.tile');
Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:3,1')->name('feedback.store');

Route::post('/api/background-image', [WallController::class, 'backgroundImage'])->name('api.background.image');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('wall');
    })->name('dashboard');

    Route::post('/api/pixels', [WallController::class, 'store'])->name('api.pixels.store');
    Route::post('/api/pixels/lock', [WallController::class, 'lockPixelArea'])->name('api.pixels.lock');
    Route::delete('/api/pixels/lock', [WallController::class, 'releasePixelAreaLock'])->name('api.pixels.lock.release');
    Route::post('/api/wall/extend', [WallController::class, 'extend'])->name('api.wall.extend');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
