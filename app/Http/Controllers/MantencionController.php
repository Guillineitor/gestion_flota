<?php

namespace App\Http\Controllers;

use App\Http\Requests\MantencionRequest;
use App\Models\Equipo;
use App\Services\HorometroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Mantenciones (preventivas y correctivas) de un equipo.
 */
class MantencionController extends Controller
{
    /**
     * Lista las mantenciones del equipo, de la más reciente a la más antigua.
     */
    public function index(Equipo $equipo): LengthAwarePaginator
    {
        return $equipo->mantenciones()->orderByDesc('fecha')->orderByDesc('id')->paginate(20);
    }

    /**
     * Registra una mantención y actualiza el horómetro del equipo.
     *
     * Responde 201 con la mantención creada, o 422 si el horómetro es
     * menor al último registrado. 
     */
    public function store(
        MantencionRequest $request,
        Equipo $equipo,
        HorometroService $horometros,
    ): JsonResponse {
        $datos = $request->validated();

        $mantencion = $horometros->registrar(
            $equipo,
            (float) $datos['horometro'],
            fn (Equipo $e) => $e->mantenciones()->create($datos),
        );

        return response()->json($mantencion, 201);
    }
}