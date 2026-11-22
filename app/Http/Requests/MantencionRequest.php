<?php

namespace App\Http\Requests;

use App\Enums\TipoMantencion;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de los datos al registrar una mantención.
 */
class MantencionRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoMantencion::class)],
            'fecha' => ['required', 'date'],
            'horometro' => ['required', 'numeric', 'min:0', 'decimal:0,1'],
            'costo_clp' => ['required', 'integer', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}