<?php

namespace App\Http\Requests;

use App\Enums\EstadoEquipo;
use App\Enums\TipoEquipo;
use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de los datos de un equipo al crearlo (POST) o editarlo (PUT/PATCH).
 */
class EquipoRequest extends FormRequest
{
    /**
     * La autorización ya la resuelve el middleware auth:sanctum de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * Al crear, los campos principales son obligatorios. Al editar se
     * pueden enviar solo los que cambian ("sometimes").
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Equipo|null $equipo Es null al crear. */
        $equipo = $this->route('equipo');

        $obligatorio = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // El código es único, pero un equipo puede conservar el suyo al editarse.
            'codigo' => [$obligatorio, 'string', 'max:50', Rule::unique('equipos', 'codigo')->ignore($equipo)],
            'tipo' => [$obligatorio, Rule::enum(TipoEquipo::class)],
            'patente' => ['nullable', 'string', 'max:10'],
            'marca' => [$obligatorio, 'string', 'max:100'],
            'modelo' => [$obligatorio, 'string', 'max:100'],

            // No se permite bajar el horómetro editando el equipo: sería una
            // forma de saltarse la regla de las cargas y mantenciones.
            'horometro_actual' => ['sometimes', 'numeric', 'min:' . ($equipo?->horometro_actual ?? 0)],

            'estado' => ['sometimes', Rule::enum(EstadoEquipo::class)],
        ];
    }
}