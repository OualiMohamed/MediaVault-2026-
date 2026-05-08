<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeLookupController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscogsController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\GoogleBooksController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\NetworkLogoController;
use App\Http\Controllers\Api\RawgController;
use App\Http\Controllers\Api\TmdbController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/tmdb/poster', [TmdbController::class, 'proxyPoster']);
Route::get('/rawg/poster', [RawgController::class, 'proxyPoster']);
Route::get('/google-books/poster', [GoogleBooksController::class, 'proxyPoster']);
Route::get('/discogs/poster', [DiscogsController::class, 'proxyPoster']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Collection routes — {type} is the library, {id} is the item
    Route::get('/collection/{type}', [CollectionController::class, 'index']);
    Route::post('/collection/{type}', [CollectionController::class, 'store']);
    Route::get('/collection/{type}/{id}', [CollectionController::class, 'show']);
    Route::put('/collection/{type}/{id}', [CollectionController::class, 'update']);
    Route::post('/collection/{type}/{id}', [CollectionController::class, 'update']); // for _method=PUT via FormData
    Route::delete('/collection/{type}/{id}', [CollectionController::class, 'destroy']);

    // routes/api.php — add inside the auth:sanctum group
    Route::post('/barcode/lookup', [BarcodeLookupController::class, 'lookup']);
    Route::get('/network-logo', [NetworkLogoController::class, 'lookup']);

    Route::post('/tmdb/search', [TmdbController::class, 'search']);
    Route::post('/tmdb/details', [TmdbController::class, 'details']);

    Route::post('/tmdb/poster', [TmdbController::class, 'poster']);

    Route::get('/export/{type}', [ExportController::class, 'export']);
    Route::post('/export/full/{type}', [ExportController::class, 'exportFullZip']); // New

    Route::post('/import/validate/{type}', [ImportController::class, 'validate']);
    Route::post('/import/execute/{type}', [ImportController::class, 'execute']);

    Route::post('/rawg/search', [RawgController::class, 'search']);
    Route::post('/rawg/details', [RawgController::class, 'details']);

    Route::get('/filters/genres/book', [CollectionController::class, 'bookGenres']);

    Route::post('/google-books/search', [GoogleBooksController::class, 'search']);
    Route::post('/google-books/details', [GoogleBooksController::class, 'details']);

    Route::post('/discogs/search', [DiscogsController::class, 'search']);
    Route::post('/discogs/details', [DiscogsController::class, 'details']);

    // Global search across all collections
    Route::get('/search', [CollectionController::class, 'globalSearch']);
});