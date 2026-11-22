<?php

namespace App\Services;

use App\Models\Equipo;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Concentra la regla del horómetro para cargas de combustible y mantenciones.
 *
 * Un horómetro menor al último registrado se rechaza. Uno mayor actualiza
 * el horómetro actual del equipo. Uno igual se acepta sin cambiar nada.
 */
class HorometroService
{
    /**
     * Registra un movimiento y actualiza el horómetro del equipo.
     *
     * Todo ocurre dentro de una transacción y con la fila del equipo
     * bloqueada (lockForUpdate). Si dos peticiones simultáneas llegan para
     * el mismo equipo, la segunda espera a la primera y valida contra el
     * horómetro ya actualizado, en vez de ambas leer el mismo valor viejo.
     * Si algo falla, no queda guardado ni el registro ni el horómetro.
     *
     * @param  Equipo  $equipo:  Equipo al que pertenece el movimiento
     * @param  float  $horometro:  Horómetro informado en el movimiento
     * @param  Closure(Equipo): Model  $crear:  Crea el registro (carga o mantención)
     *
     * @throws ValidationException Si el horómetro es menor al último registrado
     */
    public function registrar(Equipo $equipo, float $horometro, Closure $crear): Model
    {
        return DB::transaction(function () use ($equipo, $horometro, $crear) {
            // Se vuelve a leer el equipo con bloqueo para tener el valor vigente.
            $equipo = Equipo::whereKey($equipo->id)->lockForUpdate()->firstOrFail();

            if ($horometro < $equipo->horometro_actual) {
                throw ValidationException::withMessages([
                    'horometro' => sprintf(
                        'El horómetro ingresado (%s) no puede ser menor al último registrado (%s) del equipo %s.',
                        $horometro,
                        $equipo->horometro_actual,
                        $equipo->codigo,
                    ),
                ]);
            }

            $registro = $crear($equipo);

            if ($horometro > $equipo->horometro_actual) {
                $equipo->update(['horometro_actual' => $horometro]);
            }

            return $registro;
        });
    }
}