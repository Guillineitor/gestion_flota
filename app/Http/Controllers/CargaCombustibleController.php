<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaCombustibleRequest;
use App\Models\Equipo;
use App\Services\HorometroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Cargas de combustible de un equipo.
 */
class CargaCombustibleController extends Controller
{
    /**
     * Lista las cargas del equipo, de la más reciente a la más antigua.
     */
    public function index(Equipo $equipo): LengthAwarePaginator
    {
        return $equipo->cargas()->orderByDesc('fecha_hora')->paginate(20);
    }

    /**
     * Registra una carga y actualiza el horómetro del equipo.
     *
     * Responde 201 con la carga creada, o 422 si el horómetro es menor
     * al último registrado.
     */
    public function store(
        CargaCombustibleRequest $request,
        Equipo $equipo,
        HorometroService $horometros,
    ): JsonResponse {
        $datos = $request->validated();

        $carga = $horometros->registrar(
            $equipo,
            (float) $datos['horometro'],
            fn (Equipo $e) => $e->cargas()->create($datos),
        );

        return response()->json($carga, 201);
    }
}