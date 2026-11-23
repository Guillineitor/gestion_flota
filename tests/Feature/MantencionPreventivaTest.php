<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Equipos que necesitan mantención preventiva: más de 250 horas desde la
 * última preventiva (o desde cero si nunca tuvieron una).
 */
class MantencionPreventivaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un equipo con su horómetro y, opcionalmente, mantenciones.
     *
     * @param  list<array{0: string, 1: float}>  $mantenciones  [tipo, horómetro]
     */
    private function crearEquipo(string $codigo, float $horometro, array $mantenciones = []): Equipo
    {
        $equipo = Equipo::create([
            'codigo' => $codigo,
            'tipo' => 'camion',
            'marca' => 'Volvo',
            'modelo' => 'FH16',
            'horometro_actual' => $horometro,
        ]);

        foreach ($mantenciones as [$tipo, $horometroMantencion]) {
            $equipo->mantenciones()->create([
                'tipo' => $tipo,
                'fecha' => '2026-02-01',
                'horometro' => $horometroMantencion,
                'costo_clp' => 100000,
            ]);
        }

        return $equipo;
    }

    /**
     * La lista trae solo los equipos con más de 250 horas desde su última
     * preventiva. Cubre los casos de borde: exactamente 250 no entra, un
     * equipo sin preventivas cuenta desde cero, y una correctiva no
     * reinicia el conteo.
     */
    public function test_lista_equipos_sobre_250_horas(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->crearEquipo('A-300H', 400, [['preventiva', 100]]);        // 300 horas: aparece
        $this->crearEquipo('B-250H', 350, [['preventiva', 100]]);        // exactamente 250: no aparece
        $this->crearEquipo('C-NUNCA', 280);                              // nunca tuvo, 280 desde cero: aparece
        $this->crearEquipo('D-CORRECTIVA', 285, [['correctiva', 280]]);  // la correctiva no reinicia: aparece
        $this->crearEquipo('E-POCAS', 120);                              // 120 horas: no aparece

        $codigos = collect($this->getJson('/api/equipos/mantencion-preventiva')->assertOk()->json('equipos'))
            ->pluck('codigo')
            ->all();

        $this->assertEqualsCanonicalizing(['A-300H', 'C-NUNCA', 'D-CORRECTIVA'], $codigos);
    }

    /**
     * Al registrar una mantención preventiva, el equipo sale de la lista
     * porque el conteo de horas vuelve a empezar.
     */
    public function test_preventiva_saca_al_equipo_de_la_lista(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $equipo = $this->crearEquipo('A-300H', 400, [['preventiva', 100]]);

        $this->postJson("/api/equipos/{$equipo->id}/mantenciones", [
            'tipo' => 'preventiva',
            'fecha' => '2026-03-10',
            'horometro' => 410,
            'costo_clp' => 210000,
        ])->assertCreated();

        $this->assertSame([], $this->getJson('/api/equipos/mantencion-preventiva')->json('equipos'));
    }

    /**
     * Sin token la ruta responde 401 en JSON, incluso si el cliente no
     * envía el header Accept.
     */
    public function test_sin_token_responde_401(): void
    {
        $this->get('/api/equipos/mantencion-preventiva')->assertUnauthorized();
    }
}