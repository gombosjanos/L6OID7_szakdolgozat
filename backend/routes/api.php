<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FelhasznaloController;
use App\Http\Controllers\GepController;
use App\Http\Controllers\JavitandoGepController;
use App\Http\Controllers\MunkalapController;
use App\Http\Controllers\AlkatreszController;
use App\Http\Controllers\AuthController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::apiResource('felhasznalok', FelhasznaloController::class);
Route::apiResource('gepek', GepController::class);
Route::apiResource('javitando_gepek', JavitandoGepController::class);
Route::apiResource('munkalapok', MunkalapController::class);
Route::apiResource('alkatreszek', AlkatreszController::class);
