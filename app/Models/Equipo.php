<?php

namespace App\Models;

use App\Enums\EstadoEquipo;
use App\Enums\TipoEquipo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipo de la empresa.
 *
 * @property int $id
 * @property string $codigo: Código interno único de la empresa.
 * @property TipoEquipo $tipo: Tipo de equipo.
 * @property string|null $patente: Patente del equipo (opcional).
 * @property string $marca: Marca del equipo.
 * @property string $modelo: Modelo del equipo.
 * @property float $horometro_actual: Último horómetro registrado.
 * @property EstadoEquipo $estado: Estado del equipo.
 * @property \Illuminate\Support\Carbon $created_at: Fecha de creación del registro.
 * @property \Illuminate\Support\Carbon $updated_at: Fecha de última actualización del registro.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CargaCombustible> $cargas: Cargas de combustible del equipo.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Mantencion> $mantenciones: Mantenciones del equipo.
 */
class Equipo extends Model
{
    protected $table = 'equipos';

    /** Campos que se pueden asignar en masa (crear o actualizar) de un equipo. */
    protected $fillable = [
        'codigo',
        'tipo',
        'patente',
        'marca',
        'modelo',
        'horometro_actual',
        'estado',
    ];

    /**
     * Conversión automática de tipos: los enums se leen como objetos
     * y el horómetro como número en vez de texto.
     */
    protected $casts = [
        'tipo' => TipoEquipo::class,
        'estado' => EstadoEquipo::class,
        'horometro_actual' => 'float',
    ];

    /**
     * Cargas de combustible del equipo.
     *
     * @return HasMany<CargaCombustible, $this>
     */
    public function cargas(): HasMany
    {
        return $this->hasMany(CargaCombustible::class);
    }

    /**
     * Mantenciones del equipo.
     *
     * @return HasMany<Mantencion, $this>
     */
    public function mantenciones(): HasMany
    {
        return $this->hasMany(Mantencion::class);
    }
}