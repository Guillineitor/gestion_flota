<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de los datos al registrar una carga de combustible.
 */
class CargaCombustibleRequest extends FormRequest
{
    /** La autorización ya la resuelve el middleware auth:sanctum de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * El horómetro admite un solo decimal, igual que la columna de la
     * base de datos, para que no se redondee en silencio al guardarlo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date'],
            'litros' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'monto_clp' => ['required', 'integer', 'min:0'],
            'horometro' => ['required', 'numeric', 'min:0', 'decimal:0,1'],
        ];
    }
}