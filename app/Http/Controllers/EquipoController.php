<?php

namespace App\Http\Controllers;

use App\Http\Requests\EquipoRequest;
use App\Models\Equipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * CRUD de equipos de la flota (camiones, excavadoras y generadores).
 */
class EquipoController extends Controller
{
    /**
     * Lista los equipos ordenados por código, de 15 en 15.
     */
    public function index(): LengthAwarePaginator
    {
        return Equipo::orderBy('codigo')->paginate(15);
    }

    /**
     * Crea un equipo. Responde 201 con el equipo creado.
     */
    public function store(EquipoRequest $request): JsonResponse
    {
        // refresh() trae los valores por defecto que puso la base de datos
        // (estado "operativo" y horómetro 0).
        $equipo = Equipo::create($request->validated())->refresh();

        return response()->json($equipo, 201);
    }

    /**
     * Muestra un equipo. Responde 404 si no existe.
     */
    public function show(Equipo $equipo): Equipo
    {
        return $equipo;
    }

    /**
     * Edita un equipo. Solo se modifican los campos enviados.
     */
    public function update(EquipoRequest $request, Equipo $equipo): Equipo
    {
        $equipo->update($request->validated());

        return $equipo;
    }

    /**
     * Elimina un equipo.
     *
     * Responde 409 si tiene cargas o mantenciones, para no perder el
     * historial (la base de datos también lo impide con restrictOnDelete).
     */
    public function destroy(Equipo $equipo): Response|JsonResponse
    {
        if ($equipo->cargas()->exists() || $equipo->mantenciones()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un equipo con cargas o mantenciones registradas.',
            ], 409);
        }

        $equipo->delete();

        return response()->noContent();
    }
}