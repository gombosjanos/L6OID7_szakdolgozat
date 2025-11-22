<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FelhasznaloController;
use App\Http\Controllers\GepController;
use App\Http\Controllers\JavitandoGepController;
use App\Http\Controllers\MunkalapController;
use App\Http\Controllers\AlkatreszController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MunkalapNaploController;
use App\Http\Controllers\AjanlatController;
use App\Http\Controllers\MunkalapKepController;


Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Image serving endpoint stays protected; frontend fetches with auth and opens blob

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::get('/two-factor/status', [AuthController::class, 'twoFactorStatus']);
    Route::post('/two-factor/setup', [AuthController::class, 'startTwoFactorSetup']);
    Route::post('/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
    Route::post('/two-factor/disable', [AuthController::class, 'disableTwoFactor']);
    Route::post('/two-factor/recovery/regenerate', [AuthController::class, 'regenerateRecoveryCodes']);

    // Read-only for szerelő on customers; full for admin
    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::get('felhasznalok', [FelhasznaloController::class, 'index']);
        Route::get('felhasznalok/{id}', [FelhasznaloController::class, 'show']);
    });
    Route::middleware(['role:admin'])->group(function () {
        Route::post('felhasznalok', [FelhasznaloController::class, 'store']);
        Route::put('felhasznalok/{id}', [FelhasznaloController::class, 'update']);
        Route::delete('felhasznalok/{id}', [FelhasznaloController::class, 'destroy']);
    });

    // Machines: admin+szerelő can list/create/update; delete only admin
    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::get('gepek', [GepController::class, 'index']);
        Route::get('gepek/{id}', [GepController::class, 'show']);
        // Allow szerelő to create new machine
        Route::post('gepek', [GepController::class, 'store']);
    });
    Route::middleware(['role:admin'])->group(function () {
        Route::put('gepek/{id}', [GepController::class, 'update']);
        Route::delete('gepek/{id}', [GepController::class, 'destroy']);
    });

    // Parts: admin+szerelő can list/create/update; delete only admin
    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::get('alkatreszek', [AlkatreszController::class, 'index']);
        Route::get('alkatreszek/{id}', [AlkatreszController::class, 'show']);
        Route::patch('alkatreszek/{id}/keszlet', [AlkatreszController::class, 'updateKeszlet']);
    });
    Route::middleware(['role:admin'])->group(function () {
        Route::post('alkatreszek', [AlkatreszController::class, 'store']);
        Route::put('alkatreszek/{id}', [AlkatreszController::class, 'update']);
        Route::delete('alkatreszek/{id}', [AlkatreszController::class, 'destroy']);
    });

    // Read endpoints (customers can view their own records)
    Route::middleware(['role:admin,szerelo,Ugyfel'])->group(function () {
        Route::get('munkalapok', [MunkalapController::class, 'index']);
        Route::get('munkalapok/{id}', [MunkalapController::class, 'show']);
        Route::get('munkalapok/{id}/naplo', [MunkalapNaploController::class, 'index']);
        Route::get('munkalapok/{id}/ajanlat', [AjanlatController::class, 'showByWorkorder']);
        Route::get('munkalapok/{id}/kepek', [MunkalapKepController::class, 'index']);
        Route::get('munkalapok/{id}/kepek/{kep}', [MunkalapKepController::class, 'show'])->name('munkalapkepek.show');
    });
    // Modify endpoints (admin and szerelő only)
    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::post('munkalapok', [MunkalapController::class, 'store']);
        Route::patch('munkalapok/{id}', [MunkalapController::class, 'update']);
        Route::post('munkalapok/{id}/naplo', [MunkalapNaploController::class, 'store']);
        Route::patch('munkalapok/{id}/naplo/{naplo}', [MunkalapNaploController::class, 'update']);
        Route::delete('munkalapok/{id}/naplo/{naplo}', [MunkalapNaploController::class, 'destroy']);
        Route::post('munkalapok/{id}/ajanlat', [AjanlatController::class, 'upsert']);
        Route::post('munkalapok/{id}/kepek', [MunkalapKepController::class, 'store']);
        Route::delete('munkalapok/{id}/kepek/{kep}', [MunkalapKepController::class, 'destroy']);
    });
    // Customer offer decisions
    Route::middleware(['role:admin,szerelo,Ugyfel'])->group(function () {
        Route::post('munkalapok/{id}/ajanlat/accept', [AjanlatController::class, 'customerAccept']);
        Route::post('munkalapok/{id}/ajanlat/reject', [AjanlatController::class, 'customerReject']);
    });
    // Workorder delete: admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::delete('munkalapok/{id}', [MunkalapController::class, 'destroy']);
    });

    Route::apiResource('javitando_gepek', JavitandoGepController::class);
});
