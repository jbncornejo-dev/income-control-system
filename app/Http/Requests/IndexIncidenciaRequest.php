<?php

namespace App\Http\Requests;

use App\Models\Incidencia;
use Illuminate\Foundation\Http\FormRequest;

class IndexIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(
            $this->user()?->rol?->nombre_rol,
            ['administrador', 'docente', 'personal de control de ingreso'],
            true
        );
    }

    public function rules(): array
    {
        return [
            'id_examen' => ['nullable', 'integer', 'exists:examen,id_examen'],
            'tipo_incidencia' => ['nullable', 'string', 'in:' . implode(',', Incidencia::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return array_filter(parent::validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
