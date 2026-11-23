<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reporte por equipo y rango de fechas: litros, gastos y rendimiento.
 *
 * El rendimiento usa el método de estanque a estanque: los litros
 * consumidos excluyen la primera carga del rango, y las horas son la
 * diferencia de horómetro entre la primera y la última carga.
 *
 * Los datos se insertan directo con los modelos (no por la API) para que
 * estos tests prueben solo el cálculo del reporte.
 */
class ReporteTest extends TestCase
{
    use RefreshDatabase;

    /** Inicia sesión para pasar el middleware auth:sanctum. */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    /** Crea un equipo con el código indicado. */
    private function crearEquipo(string $codigo = 'EXC-T1'): Equipo
    {
        return Equipo::create([
            'codigo' => $codigo,
            'tipo' => 'excavadora',
            'marca' => 'Caterpillar',
            'modelo' => '320',
        ]);
    }

    /** Registra una carga de combustible directamente en la base. */
    private function cargar(Equipo $equipo, string $fechaHora, float $litros, int $monto, float $horometro): void
    {
        $equipo->cargas()->create([
            'fecha_hora' => $fechaHora,
            'litros' => $litros,
            'monto_clp' => $monto,
            'horometro' => $horometro,
        ]);
    }

    /** Registra una mantención directamente en la base. */
    private function mantener(Equipo $equipo, string $fecha, int $costo, string $tipo = 'correctiva'): void
    {
        $equipo->mantenciones()->create([
            'tipo' => $tipo,
            'fecha' => $fecha,
            'horometro' => 0,
            'costo_clp' => $costo,
        ]);
    }

    /**
     * Pide el reporte de marzo de 2026 y devuelve el JSON.
     *
     * @return array<string, mixed>
     */
    private function reporteDeMarzo(Equipo $equipo): array
    {
        return $this->getJson("/api/equipos/{$equipo->id}/reporte?desde=2026-03-01&hasta=2026-03-31")
            ->assertOk()
            ->json();
    }

    /**
     * Con tres cargas y una mantención, el reporte suma litros y gastos
     * y calcula el rendimiento: (100 + 100) litros / 100 horas = 2.
     */
    public function test_reporte_calcula_totales(): void
    {
        $equipo = $this->crearEquipo();
        $this->cargar($equipo, '2026-03-10 08:00:00', 80, 90000, 140);
        $this->cargar($equipo, '2026-03-15 08:00:00', 100, 110000, 190);
        $this->cargar($equipo, '2026-03-20 08:00:00', 100, 108000, 240);
        $this->mantener($equipo, '2026-03-12', 300000);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(3, $reporte['cargas_consideradas']);
        $this->assertEquals(280, $reporte['litros_totales']);
        $this->assertEquals(308000, $reporte['gasto_combustible_clp']);
        $this->assertEquals(300000, $reporte['gasto_mantenciones_clp']);
        $this->assertEquals(100, $reporte['horas_de_uso']);
        // La primera carga no cuenta como consumo del período.
        $this->assertEquals(2, $reporte['rendimiento_l_por_hora']);
    }

    /**
     * Las cargas y mantenciones de un instante antes o después del rango
     * no entran al reporte.
     */
    public function test_reporte_ignora_fuera_del_rango(): void
    {
        $equipo = $this->crearEquipo();
        $this->cargar($equipo, '2026-03-10 08:00:00', 80, 90000, 140);
        $this->cargar($equipo, '2026-03-20 08:00:00', 100, 108000, 240);
        $this->mantener($equipo, '2026-03-12', 300000);

        // Un instante antes y un instante después de marzo.
        $this->cargar($equipo, '2026-02-28 23:59:59', 500, 600000, 100);
        $this->cargar($equipo, '2026-04-01 00:00:00', 500, 600000, 300);
        $this->mantener($equipo, '2026-02-28', 999999);
        $this->mantener($equipo, '2026-04-01', 999999);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(2, $reporte['cargas_consideradas']);
        $this->assertEquals(180, $reporte['litros_totales']);
        $this->assertEquals(300000, $reporte['gasto_mantenciones_clp']);
    }

    /**
     * El primer y el último día del rango cuentan completos: una carga a
     * las 00:00 del primer día y otra a las 23:30 del último entran.
     */
    public function test_reporte_incluye_dias_extremos(): void
    {
        $equipo = $this->crearEquipo();
        $this->cargar($equipo, '2026-03-01 00:00:00', 50, 55000, 100);
        $this->cargar($equipo, '2026-03-31 23:30:00', 50, 55000, 150);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(2, $reporte['cargas_consideradas']);
    }

    /**
     * Con una sola carga no hay forma de medir el consumo, así que el
     * rendimiento es null (pero los litros sí se suman).
     */
    public function test_rendimiento_null_con_una_carga(): void
    {
        $equipo = $this->crearEquipo();
        $this->cargar($equipo, '2026-03-10 08:00:00', 80, 90000, 140);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(1, $reporte['cargas_consideradas']);
        $this->assertEquals(80, $reporte['litros_totales']);
        $this->assertNull($reporte['rendimiento_l_por_hora']);
    }

    /**
     * Si las dos cargas tienen el mismo horómetro, las horas de uso son
     * cero: el rendimiento es null en vez de dividir por cero.
     */
    public function test_rendimiento_null_sin_horas(): void
    {
        $equipo = $this->crearEquipo();
        $this->cargar($equipo, '2026-03-10 08:00:00', 80, 90000, 140);
        $this->cargar($equipo, '2026-03-11 08:00:00', 60, 66000, 140);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(0, $reporte['horas_de_uso']);
        $this->assertNull($reporte['rendimiento_l_por_hora']);
    }

    /**
     * Un rango sin cargas ni mantenciones devuelve todo en cero y el
     * rendimiento en null.
     */
    public function test_reporte_sin_movimientos(): void
    {
        $equipo = $this->crearEquipo();

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(0, $reporte['cargas_consideradas']);
        $this->assertEquals(0, $reporte['litros_totales']);
        $this->assertEquals(0, $reporte['gasto_combustible_clp']);
        $this->assertEquals(0, $reporte['gasto_mantenciones_clp']);
        $this->assertNull($reporte['rendimiento_l_por_hora']);
    }

    /**
     * El reporte de un equipo no suma cargas ni mantenciones de otro.
     */
    public function test_reporte_no_mezcla_equipos(): void
    {
        $equipo = $this->crearEquipo('EXC-T1');
        $otro = $this->crearEquipo('EXC-T2');
        $this->cargar($equipo, '2026-03-10 08:00:00', 80, 90000, 140);
        $this->cargar($otro, '2026-03-10 08:00:00', 999, 999000, 500);
        $this->mantener($otro, '2026-03-12', 999999);

        $reporte = $this->reporteDeMarzo($equipo);

        $this->assertEquals(80, $reporte['litros_totales']);
        $this->assertEquals(0, $reporte['gasto_mantenciones_clp']);
    }

    /**
     * Las fechas del rango son obligatorias, deben tener formato Y-m-d y
     * "hasta" no puede ser anterior a "desde": en cualquier otro caso, 422.
     */
    public function test_reporte_valida_fechas(): void
    {
        $equipo = $this->crearEquipo();
        $base = "/api/equipos/{$equipo->id}/reporte";

        $this->getJson("{$base}?desde=2026-03-31&hasta=2026-03-01")->assertUnprocessable();
        $this->getJson("{$base}?desde=2026-03-01")->assertUnprocessable();
        $this->getJson("{$base}?desde=hoy&hasta=mañana")->assertUnprocessable();
    }

    /**
     * Pedir el reporte de un equipo que no existe responde 404.
     */
    public function test_reporte_equipo_inexistente(): void
    {
        $this->getJson('/api/equipos/999/reporte?desde=2026-03-01&hasta=2026-03-31')
            ->assertNotFound();
    }
}