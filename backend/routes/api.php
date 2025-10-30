<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FelhasznaloController;
use App\Http\Controllers\GepController;
use App\Http\Controllers\JavitandoGepController;
use App\Http\Controllers\MunkalapController;
use App\Http\Controllers\AlkatreszController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MunkalapNaploController;
use App\Http\Controllers\AjanlatController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

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
        Route::post('gepek', [GepController::class, 'store']);
        Route::put('gepek/{id}', [GepController::class, 'update']);
    });
    Route::middleware(['role:admin'])->group(function () {
        Route::delete('gepek/{id}', [GepController::class, 'destroy']);
    });

    // Parts: admin+szerelő can list/create/update; delete only admin
    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::get('alkatreszek', [AlkatreszController::class, 'index']);
        Route::get('alkatreszek/{id}', [AlkatreszController::class, 'show']);
        Route::post('alkatreszek', [AlkatreszController::class, 'store']);
        Route::put('alkatreszek/{id}', [AlkatreszController::class, 'update']);
    });
    Route::middleware(['role:admin'])->group(function () {
        Route::delete('alkatreszek/{id}', [AlkatreszController::class, 'destroy']);
    });

    Route::middleware(['role:admin,szerelo'])->group(function () {
        Route::apiResource('munkalapok', MunkalapController::class);
        Route::get('munkalapok/{id}/naplo', [MunkalapNaploController::class, 'index']);
        Route::post('munkalapok/{id}/naplo', [MunkalapNaploController::class, 'store']);
        Route::get('munkalapok/{id}/ajanlat', [AjanlatController::class, 'showByWorkorder']);
        Route::post('munkalapok/{id}/ajanlat', [AjanlatController::class, 'upsert']);
    });

    Route::apiResource('javitando_gepek', JavitandoGepController::class);
});
