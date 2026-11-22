<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Pública. Limitada a 5 intentos por minuto para frenar fuerza bruta.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Todo lo que está aquí dentro exige un token válido (de Sanctum).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
});
