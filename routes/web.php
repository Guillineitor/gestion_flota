<?php

use Illuminate\Support\Facades\Route;

// Toda ruta que no sea /api devuelve la app de React, que decide qué pantalla mostrar.
Route::view('/{ruta?}', 'reporte')->where('ruta', '^(?!api(/|$)).*$');