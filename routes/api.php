<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\MantencionController;
use App\Http\Controllers\MantencionPreventivaController;
use App\Http\Controllers\ReporteEquipoController;

// Pública. Limitada a 5 intentos por minuto para frenar fuerza bruta.
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Todo lo que está aquí dentro exige un token válido (de Sanctum).
Route::middleware('auth:sanctum')->group(function () {
    // Logout: invalida el token actual.
    Route::post('logout', [AuthController::class, 'logout']);

    // Lista los equipos que tienen mantención preventiva pendiente.
    Route::get('equipos/mantencion-preventiva', MantencionPreventivaController::class);

    // Genera index, store, show, update y destroy para /api/equipos.
    Route::apiResource('equipos', EquipoController::class);

    // Rutas anidadas para las cargas de combustible.
    // GET: Para listar las cargas de un equipo.
    // POST para registrar una nueva carga.
    Route::get('equipos/{equipo}/cargas-combustible', [CargaCombustibleController::class, 'index']);
    Route::post('equipos/{equipo}/cargas-combustible', [CargaCombustibleController::class, 'store']);

    // Rutas anidadas para las mantenciones.
    // GET: Para listar las mantenciones de un equipo.
    // POST para registrar una nueva mantención.
    Route::get('equipos/{equipo}/mantenciones', [MantencionController::class, 'index']);
    Route::post('equipos/{equipo}/mantenciones', [MantencionController::class, 'store']);

    // Ruta para obtener el reporte de un equipo en un rango de fechas.
    Route::get('equipos/{equipo}/reporte', ReporteEquipoController::class);

});
