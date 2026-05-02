<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeLookupController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NetworkLogoController;
use App\Http\Controllers\Api\TmdbController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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
});