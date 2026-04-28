<?php

use Illuminate\Support\Facades\Route;

// Catch-all route to serve the SPA
Route::view('/{any}', 'app')->where('any', '.*');
