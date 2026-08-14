<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromoController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::delete('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/promo/claim', [PromoController::class, 'claim']);
    Route::get('/promo/history', [PromoController::class, 'history']);
    Route::patch('/promo/{claim}/revoke', [PromoController::class, 'revoke']);
});
