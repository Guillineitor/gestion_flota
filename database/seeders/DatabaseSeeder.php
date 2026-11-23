<?php

namespace Database\Seeders;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo para probar la API.
 *
 * Se ejecuta con: php artisan migrate:fresh --seed
 *
 * Crea un usuario administrador y seis equipos con cargas y mantenciones
 * pensados para cubrir cada caso: con y sin preventiva previa, justo en el
 * límite de 250 horas, y equipos en distintos estados. Las fechas son fijas
 * (agosto a noviembre de 2026) para que los reportes sean reproducibles.
 */
class DatabaseSeeder extends Seeder
{
    /** Precio de referencia del diésel, en pesos chilenos por litro. */
    private const PRECIO_LITRO = 1504;

    /**
     * Crea el usuario y los equipos con su historial.
     */
    public function run(): void
    {
        // Usuario para entrar a la API (la clave se guarda con hash).
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@construckit.test',
            'password' => 'password',
        ]);

        // 200 horas desde su preventiva: no necesita mantención.
        $this->crearEquipo(
            ['codigo' => 'CAMION-1', 'tipo' => 'camion', 'patente' => 'ABCD12', 'marca' => 'Volvo', 'modelo' => 'FH16'],
            cargas: [
                ['2026-09-05 08:00:00', 90, 150],
                ['2026-09-20 08:00:00', 95, 200],
                ['2026-10-05 08:00:00', 92, 250],
                ['2026-10-20 08:00:00', 98, 300],
            ],
            mantenciones: [
                ['preventiva', '2026-08-20', 100, 220000, 'Cambio de aceite y filtros'],
            ],
        );

        // 300 horas desde su preventiva: necesita mantención.
        $this->crearEquipo(
            ['codigo' => 'CAMION-2', 'tipo' => 'camion', 'patente' => 'EFGH34', 'marca' => 'Scania', 'modelo' => 'R450'],
            cargas: [
                ['2026-08-10 08:00:00', 150, 200],
                ['2026-09-10 08:00:00', 148, 300],
                ['2026-10-12 08:00:00', 152, 400],
            ],
            mantenciones: [
                ['preventiva', '2026-07-15', 100, 210000, 'Cambio de aceite y filtros'],
            ],
        );

        // Exactamente 250 horas desde su preventiva: NO necesita mantención.
        $this->crearEquipo(
            ['codigo' => 'CAMION-3', 'tipo' => 'camion', 'patente' => 'IJKL56', 'marca' => 'Mercedes-Benz', 'modelo' => 'Actros', 'estado' => 'fuera_de_servicio'],
            cargas: [
                ['2026-09-15 08:00:00', 100, 250],
                ['2026-10-15 08:00:00', 105, 350],
            ],
            mantenciones: [
                ['preventiva', '2026-08-01', 100, 205000, 'Cambio de aceite y filtros'],
            ],
        );

        // Nunca tuvo preventiva: se cuenta desde cero (280 horas), necesita
        // mantención.
        $this->crearEquipo(
            ['codigo' => 'EXCAVADORA-1', 'tipo' => 'excavadora', 'marca' => 'Caterpillar', 'modelo' => '320'],
            cargas: [
                ['2026-08-25 08:00:00', 50, 60],
                ['2026-09-12 08:00:00', 55, 120],
                ['2026-10-03 08:00:00', 80, 200],
                ['2026-10-25 08:00:00', 80, 280],
            ],
            mantenciones: [
                ['correctiva', '2026-10-10', 210, 90000, 'Reparación de manguera hidráulica'],
            ],
        );

        // En mantención: tiene una correctiva en curso. Solo 130 horas desde
        // su preventiva, así que no entra en la lista de preventivas.
        $this->crearEquipo(
            ['codigo' => 'EXCAVADORA-2', 'tipo' => 'excavadora', 'marca' => 'Komatsu', 'modelo' => 'PC210', 'estado' => 'en_mantencion'],
            cargas: [
                ['2026-09-20 08:00:00', 60, 560],
                ['2026-10-18 08:00:00', 65, 620],
            ],
            mantenciones: [
                ['preventiva', '2026-09-01', 500, 230000, 'Cambio de aceite, filtros y revisión general'],
                ['correctiva', '2026-11-18', 630, 480000, 'Reparación del sistema hidráulico'],
            ],
        );

        // Generador sin patente y sin preventiva previa: 120 horas, no necesita mantención.
        $this->crearEquipo(
            ['codigo' => 'GENERADOR-1', 'tipo' => 'generador', 'marca' => 'Cummins', 'modelo' => 'C100'],
            cargas: [
                ['2026-09-10 08:00:00', 30, 40],
                ['2026-10-10 08:00:00', 32, 80],
                ['2026-11-10 08:00:00', 31, 120],
            ],
        );
    }

    /**
     * Crea un equipo con su historial de cargas y mantenciones.
     *
     * El horómetro actual del equipo se calcula como el mayor horómetro de
     * su historial, igual que lo mantiene la API. Los montos de
     * las cargas se calculan con el precio de referencia por litro.
     *
     * @param  array<string, mixed>  $datos  Campos del equipo (sin horómetro)
     * @param  list<array{0: string, 1: int, 2: int}>  $cargas  [fecha_hora, litros, horómetro]
     * @param  list<array{0: string, 1: string, 2: int, 3: int, 4: string}>  $mantenciones  [tipo, fecha, horómetro, costo_clp, descripción]
     */
    private function crearEquipo(array $datos, array $cargas, array $mantenciones = []): void
    {
        $equipo = Equipo::create($datos + [
            'horometro_actual' => max([
                ...array_column($cargas, 2),
                ...array_column($mantenciones, 2),
            ]),
        ]);

        foreach ($cargas as [$fechaHora, $litros, $horometro]) {
            $equipo->cargas()->create([
                'fecha_hora' => $fechaHora,
                'litros' => $litros,
                'monto_clp' => $litros * self::PRECIO_LITRO,
                'horometro' => $horometro,
            ]);
        }

        foreach ($mantenciones as [$tipo, $fecha, $horometro, $costo, $descripcion]) {
            $equipo->mantenciones()->create([
                'tipo' => $tipo,
                'fecha' => $fecha,
                'horometro' => $horometro,
                'costo_clp' => $costo,
                'descripcion' => $descripcion,
            ]);
        }
    }
}