<?php

namespace App\Http\Controllers;

use App\Enums\TipoMantencion;
use App\Models\Equipo;
use Illuminate\Http\JsonResponse;

/**
 * Lista los equipos que necesitan mantención preventiva.
 */
class MantencionPreventivaController extends Controller
{
    /** Horas de horómetro entre una mantención preventiva y la siguiente. */
    private const UMBRAL_HORAS = 250;

    /**
     * Devuelve los equipos con más de 250 horas desde su última
     * mantención preventiva (o desde cero, si nunca tuvieron una).
     *
     * El filtro se hace en la base de datos con una subconsulta, así que
     * no se cargan todos los equipos en memoria. La subconsulta usa el
     * índice (equipo_id, tipo) de la tabla de mantenciones.
     */
    public function __invoke(): JsonResponse
    {
        $preventiva = TipoMantencion::Preventiva->value;

        $equipos = Equipo::query()
            // Trae el horómetro de la última preventiva como "horometro_ultima_preventiva".
            ->withMax(
                ['mantenciones as horometro_ultima_preventiva' => fn ($consulta) => $consulta->where('tipo', $preventiva)],
                'horometro',
            )
            // Solo los que tienen más de 250 horas desde la última preventiva.
            ->whereRaw(
                'equipos.horometro_actual - COALESCE(
                    (SELECT MAX(m.horometro) FROM mantenciones m
                     WHERE m.equipo_id = equipos.id AND m.tipo = ?), 0
                ) > ?',
                [$preventiva, self::UMBRAL_HORAS],
            )
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'umbral_horas' => self::UMBRAL_HORAS,
            'equipos' => $equipos->map(function (Equipo $equipo) {
                $ultima = (float) ($equipo->horometro_ultima_preventiva ?? 0);

                return [
                    'id' => $equipo->id,
                    'codigo' => $equipo->codigo,
                    'tipo' => $equipo->tipo,
                    'horometro_actual' => $equipo->horometro_actual,
                    'horometro_ultima_preventiva' => $ultima,
                    'horas_desde_ultima_preventiva' => round($equipo->horometro_actual - $ultima, 1),
                ];
            })->values(),
        ]);
    }
}