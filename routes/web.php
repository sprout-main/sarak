<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorageFileController;
use App\Http\Controllers\TileProxyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('map');
})->name('map');

Route::get('/public/storage/{path}', [StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('public.storage.file');

Route::get('/storage/{path}', [StorageFileController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.file');

Route::get('/tiles/{style}/{z}/{x}/{y}.png', [TileProxyController::class, 'show'])
    ->whereIn('style', ['dark', 'light'])
    ->whereNumber(['z', 'x', 'y'])
    ->name('tiles.show');

Route::get('/tiles/{style}/{z}/{x}/{y}@2x.png', [TileProxyController::class, 'show'])
    ->whereIn('style', ['dark', 'light'])
    ->whereNumber(['z', 'x', 'y'])
    ->defaults('retina', 1)
    ->name('tiles.show.retina');

Route::get('/dashboard', function () {
    if (auth()->user()?->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/admin/queue', function () {
        return view('admin.queue');
    })->name('admin.queue');

    Route::get('/admin/places', function () {
        return view('admin.places');
    })->name('admin.places');

    Route::get('/admin/import', function () {
        return view('admin.import');
    })->name('admin.import');

    Route::get('/admin/boundaries', function () {
        return view('admin.boundaries');
    })->name('admin.boundaries');

    Route::get('/admin/analytics', function () {
        return view('admin.analytics');
    })->name('admin.analytics');

    Route::get('/admin/users', function () {
        return view('admin.users');
    })->name('admin.users');

    Route::get('/admin/reports', function () {
        return view('admin.reports');
    })->name('admin.reports');

    Route::get('/admin/audit-logs', function () {
        return view('admin.audit-logs');
    })->name('admin.audit-logs');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/contributor-privacy', [ProfileController::class, 'updateContributorPrivacy'])->name('profile.contributor-privacy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';