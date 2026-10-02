<?php

use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\BoundaryController;
use App\Http\Controllers\Api\Admin\DataQualityController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\PlaceAdminController;
use App\Http\Controllers\Api\Admin\QueueController;
use App\Http\Controllers\Api\Admin\ReportAdminController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContributorController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GeoUtilityController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\PlaceReportController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/barangays', [BarangayController::class, 'index']);
Route::get('/boundaries', [BarangayController::class, 'boundaries']);
Route::get('/places', [PlaceController::class, 'index']);
Route::get('/places/{place}', [PlaceController::class, 'show']);
Route::get('/geo/barangay', [GeoUtilityController::class, 'resolveBarangay']);
Route::get('/route', [RouteController::class, 'route']);
Route::get('/search/suggest', [SearchController::class, 'suggest']);
Route::get('/search/semantic', [SearchController::class, 'semantic']);
Route::get('/contributors/leaderboard', [ContributorController::class, 'leaderboard']);
Route::get('/export.geojson', [ExportController::class, 'geojson']);
Route::get('/export.csv', [ExportController::class, 'csv']);

Route::middleware('auth')->group(function () {
    Route::post('/places', [PlaceController::class, 'store']);
    Route::post('/places/{place}/reports', [PlaceReportController::class, 'store']);
    Route::post('/places/{place}/photos', [PlaceController::class, 'storePhoto']);
    Route::delete('/places/{place}/photos/{photo}', [PlaceController::class, 'destroyPhoto'])
        ->name('places.photos.destroy');
    Route::patch('/places/{place}/photos/{photo}/cover', [PlaceController::class, 'updateCoverPhoto'])
        ->name('places.photos.cover');
});

Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/queue', [QueueController::class, 'index']);
    Route::post('/queue/{queue}/approve', [QueueController::class, 'approve']);
    Route::post('/queue/{queue}/reject', [QueueController::class, 'reject']);
    Route::delete('/queue/{queue}', [QueueController::class, 'destroy']);
    Route::get('/places', [PlaceAdminController::class, 'index']);
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/reports', [ReportAdminController::class, 'index']);
    Route::patch('/reports/{report}/status', [ReportAdminController::class, 'updateStatus']);
    Route::delete('/reports/{report}', [ReportAdminController::class, 'destroy']);
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');

    Route::middleware('role:admin')->group(function () {
        Route::post('/places', [PlaceAdminController::class, 'store']);
        Route::post('/places/mass-relocate', [PlaceAdminController::class, 'massRelocate']);
        Route::post('/queue/{queue}/merge', [QueueController::class, 'merge']);
        Route::patch('/places/{place}', [PlaceAdminController::class, 'update']);
        Route::patch('/places/{place}/location', [PlaceAdminController::class, 'updateLocation']);
        Route::delete('/places/{place}', [PlaceAdminController::class, 'destroy']);

        // Administrator photos publish immediately, mirroring how admin place
        // creation bypasses the queue.
        Route::get('/places/{place}/photos', [PlaceAdminController::class, 'indexPhotos']);
        Route::post('/places/{place}/photos', [PlaceAdminController::class, 'storePhoto']);
        Route::delete('/places/{place}/photos/{photo}', [PlaceAdminController::class, 'destroyPhoto'])
            ->name('admin.places.photos.destroy');
        Route::patch('/places/{place}/photos/{photo}/cover', [PlaceAdminController::class, 'updateCoverPhoto'])
            ->name('admin.places.photos.cover');

        Route::post('/import', [ImportController::class, 'run']);
        Route::get('/imports', [ImportController::class, 'index']);
        Route::get('/boundaries', [BoundaryController::class, 'index']);
        Route::get('/analytics/coverage', [AnalyticsController::class, 'coverage']);
        Route::get('/analytics/pending', [AnalyticsController::class, 'pending']);
        Route::get('/quality/barangay', [DataQualityController::class, 'barangay']);
        Route::post('/quality/barangay/fix', [DataQualityController::class, 'fixBarangay']);
    });
});
