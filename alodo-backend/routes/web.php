<?php

use Illuminate\Support\Facades\Route;

Route::view('/swagger', 'swagger')->name('swagger');

Route::get('/', function () {
    return view('welcome');
});
