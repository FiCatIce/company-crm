<?php

use App\Http\Controllers\Api\CtiCallController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// CTI ingest — server-to-server from the PBX/connector. Stateless bearer token
// (Sanctum, ability cti:ingest); no CSRF, no session (api group is stateless).
Route::post('cti/calls', CtiCallController::class)
    ->middleware(['auth:sanctum', 'throttle:cti'])
    ->name('cti.calls.store');
