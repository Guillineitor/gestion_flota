<?php

namespace App\Http\Controllers;

use App\Models\CargaCombustible;
use App\Models\Equipo;
use App\Models\Mantencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reporte de un equipo de la flota en un rango de fechas, que devuelve el gasto y litros
 * de combustible, gasto de mantenciones y rendimiento promedio en litros por hora de uso.
 */
class ReporteEquipoController extends Controller
{
    /**
     * Devuelve los totales del equipo entre "desde" y "hasta" (ambos
     * días incluidos, formato Y-m-d).
     *
     * Los días se interpretan en la zona horaria de la aplicación
     * (America/Santiago): "hasta" llega hasta las 23:59:59 de ese día.
     *
     * El rendimiento (litros por hora) usa el siguiente método: 
     * cada carga de combustible repone lo consumido desde la anterior, así que
     * los litros consumidos excluyen la primera carga del rango y las
     * horas son la diferencia de horómetro entre la primera y la última carga.
     * Es null si hay menos de 2 cargas o si las horas de uso son 0.
     */
    public function __invoke(Request $request, Equipo $equipo): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $desde = Carbon::parse($datos['desde'])->startOfDay();
        $hasta = Carbon::parse($datos['hasta'])->endOfDay();

        $cargas = CargaCombustible::query()
            ->where('equipo_id', $equipo->id)
            ->whereBetween('fecha_hora', [$desde, $hasta]);

        // Una sola consulta agregada en vez de cargar todas las filas.
        $totales = (clone $cargas)->toBase()->selectRaw(
            'COUNT(*) AS cantidad,
             COALESCE(SUM(litros), 0) AS litros,
             COALESCE(SUM(monto_clp), 0) AS monto,
             MIN(horometro) AS horometro_min,
             MAX(horometro) AS horometro_max'
        )->first();

        $gastoMantenciones = (int) Mantencion::query()
            ->where('equipo_id', $equipo->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->sum('costo_clp');

        $horas = (int) $totales->cantidad >= 2
            ? (float) $totales->horometro_max - (float) $totales->horometro_min
            : 0.0;

        $rendimiento = null;

        if ($horas > 0) {
            // La primera carga del rango no cuenta como consumo del período.
            $primera = (clone $cargas)
                ->orderBy('horometro')
                ->orderBy('fecha_hora')
                ->orderBy('id')
                ->first();

            $litrosConsumidos = (float) $totales->litros - $primera->litros;
            $rendimiento = round($litrosConsumidos / $horas, 2);
        }

        return response()->json([
            'equipo' => ['id' => $equipo->id, 'codigo' => $equipo->codigo],
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'cargas_consideradas' => (int) $totales->cantidad,
            'litros_totales' => round((float) $totales->litros, 2),
            'gasto_combustible_clp' => (int) $totales->monto,
            'gasto_mantenciones_clp' => $gastoMantenciones,
            'horas_de_uso' => $horas,
            'rendimiento_l_por_hora' => $rendimiento,
        ]);
    }
}