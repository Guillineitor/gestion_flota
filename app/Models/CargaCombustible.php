<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carga de combustible de un equipo.
 *
 * @property int $id
 * @property int $equipo_id: ID del equipo al que pertenece la carga.
 * @property \Illuminate\Support\Carbon $fecha_hora: Fecha y hora de la carga.
 * @property float $litros: Cantidad de litros cargados.
 * @property int $monto_clp: Monto en pesos chilenos.
 * @property float $horometro: Horómetro del equipo al cargar.
 * @property \Illuminate\Support\Carbon $created_at: Fecha de creación del registro.
 * @property \Illuminate\Support\Carbon $updated_at: Fecha de última actualización del registro.
 * @property-read Equipo $equipo: Equipo al que pertenece la carga.
 */
class CargaCombustible extends Model
{
    protected $table = 'cargas_combustible';

    // Campos que se pueden asignar en masa (crear o actualizar) de una carga de combustible.
    protected $fillable = [
        'equipo_id',
        'fecha_hora',
        'litros',
        'monto_clp',
        'horometro',
    ];

    // Conversión automática de tipos: el horómetro y los litros se leen como números en vez de texto.
    protected $casts = [
        'fecha_hora' => 'datetime',
        'litros' => 'float',
        'horometro' => 'float',
        'monto_clp' => 'integer',
    ];

    /**
     * Equipo al que pertenece la carga.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}