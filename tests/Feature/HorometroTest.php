<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regla del horómetro al registrar cargas de combustible y mantenciones.
 *
 * - Horómetro mayor al actual: se registra y el equipo sube.
 * - Horómetro igual: se registra y el equipo no cambia.
 * - Horómetro menor: 422 con mensaje claro y no se guarda nada.
 */
class HorometroTest extends TestCase
{
    use RefreshDatabase;

    /** Inicia sesión para pasar el middleware auth:sanctum. */
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    /** Crea un equipo con el horómetro indicado. */
    private function crearEquipo(float $horometro = 100): Equipo
    {
        return Equipo::create([
            'codigo' => 'CAM-T1',
            'tipo' => 'camion',
            'marca' => 'Volvo',
            'modelo' => 'FH16',
            'horometro_actual' => $horometro,
        ]);
    }

    /**
     * Datos válidos de una carga, con los cambios que pida cada prueba.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosCarga(array $cambios = []): array
    {
        return array_merge([
            'fecha_hora' => '2026-03-10 08:00:00',
            'litros' => 80,
            'monto_clp' => 90000,
            'horometro' => 140,
        ], $cambios);
    }

    /**
     * Datos válidos de una mantención, con los cambios que pida cada prueba.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosMantencion(array $cambios = []): array
    {
        return array_merge([
            'tipo' => 'preventiva',
            'fecha' => '2026-03-10',
            'horometro' => 140,
            'costo_clp' => 250000,
        ], $cambios);
    }

    /**
     * Una carga con horómetro mayor al actual se guarda (201) y el
     * horómetro del equipo sube a ese valor.
     */
    public function test_carga_sube_el_horometro(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 140]))
            ->assertCreated();

        $this->assertEquals(140, $equipo->fresh()->horometro_actual);
        $this->assertDatabaseCount('cargas_combustible', 1);
    }

    /**
     * Una carga con el mismo horómetro que el equipo se acepta, y el
     * horómetro del equipo queda igual.
     */
    public function test_carga_con_horometro_igual(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 100]))
            ->assertCreated();

        $this->assertEquals(100, $equipo->fresh()->horometro_actual);
        $this->assertDatabaseCount('cargas_combustible', 1);
    }

    /**
     * Una carga con horómetro menor al actual responde 422 y no deja
     * rastro, ni la carga ni un cambio en el equipo.
     */
    public function test_carga_con_horometro_menor_falla(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 90]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horometro');

        $this->assertEquals(100, $equipo->fresh()->horometro_actual);
        $this->assertDatabaseCount('cargas_combustible', 0);
    }

    /**
     * El mensaje de error menciona el horómetro ingresado, el último
     * registrado y el código del equipo, para que sea claro.
     */
    public function test_mensaje_de_error_claro(): void
    {
        $equipo = $this->crearEquipo(100);

        $mensaje = $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 90]))
            ->assertUnprocessable()
            ->json('errors.horometro.0');

        $this->assertStringContainsString('90', $mensaje);
        $this->assertStringContainsString('100', $mensaje);
        $this->assertStringContainsString('CAM-T1', $mensaje);
    }

    /**
     * Un horómetro con más de un decimal se rechaza, porque la columna
     * guarda solo uno y no se quiere redondear.
     */
    public function test_horometro_con_dos_decimales_falla(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 160.25]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horometro');
    }

    /**
     * Una mantención con horómetro mayor al actual se guarda (201) y el
     * horómetro del equipo sube.
     */
    public function test_mantencion_sube_el_horometro(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/mantenciones", $this->datosMantencion(['horometro' => 150]))
            ->assertCreated();

        $this->assertEquals(150, $equipo->fresh()->horometro_actual);
        $this->assertDatabaseCount('mantenciones', 1);
    }

    /**
     * Una mantención con horómetro menor al actual responde 422 y no
     * guarda nada.
     */
    public function test_mantencion_con_horometro_menor_falla(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/mantenciones", $this->datosMantencion(['horometro' => 50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horometro');

        $this->assertEquals(100, $equipo->fresh()->horometro_actual);
        $this->assertDatabaseCount('mantenciones', 0);
    }

    /**
     * Un tipo de mantención que no es preventiva ni correctiva se
     * rechaza con 422.
     */
    public function test_mantencion_con_tipo_invalido_falla(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/mantenciones", $this->datosMantencion(['tipo' => 'cosmetica']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo');
    }

    /**
     * Cargas y mantenciones comparten la misma regla: si una mantención
     * sube el horómetro a 150, una carga posterior con 120 ya es retroceder.
     */
    public function test_carga_y_mantencion_comparten_la_regla(): void
    {
        $equipo = $this->crearEquipo(100);

        $this->postJson("/api/equipos/{$equipo->id}/mantenciones", $this->datosMantencion(['horometro' => 150]))
            ->assertCreated();

        $this->postJson("/api/equipos/{$equipo->id}/cargas-combustible", $this->datosCarga(['horometro' => 120]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('horometro');
    }
}