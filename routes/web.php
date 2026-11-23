<?php

use Illuminate\Support\Facades\Route;

Route::view('/reporte', 'reporte');

Route::get('/', function () {
    return view('welcome');
});
