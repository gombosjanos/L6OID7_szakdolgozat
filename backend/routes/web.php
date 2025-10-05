<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/hash/{pwd}', function ($pwd) {
    return Hash::make($pwd);
});
