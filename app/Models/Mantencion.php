<?php

namespace App\Models;

use App\Enums\TipoMantencion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mantención de un equipo.
 *
 * @property int $id
 * @property int $equipo_id: ID del equipo al que pertenece la mantención.
 * @property TipoMantencion $tipo: Tipo de mantención (preventiva o correctiva)
 * @property \Illuminate\Support\Carbon $fecha: Fecha de la mantención.
 * @property float $horometro: Horómetro del equipo al mantener.
 * @property int $costo_clp: Costo en pesos chilenos.
 * @property string|null $descripcion: Descripción de la mantención.
 * @property \Illuminate\Support\Carbon $created_at: Fecha de creación del registro.
 * @property \Illuminate\Support\Carbon $updated_at: Fecha de última actualización del registro.
 * @property-read Equipo $equipo: Equipo al que pertenece la mantención.
 */
class Mantencion extends Model
{
    protected $table = 'mantenciones';

    // Campos que se pueden asignar en masa (crear o actualizar) de una mantención.
    protected $fillable = [
        'equipo_id',
        'tipo',
        'fecha',
        'horometro',
        'costo_clp',
        'descripcion',
    ];

    // Conversión automática de tipos: los enums se leen como objetos
    // y el horómetro como número en vez de texto.
    protected $casts = [
        'tipo' => TipoMantencion::class,
        'fecha' => 'date:Y-m-d',
        'horometro' => 'float',
        'costo_clp' => 'integer',
    ];

    /**
     * Equipo al que pertenece la mantención.
     *
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}