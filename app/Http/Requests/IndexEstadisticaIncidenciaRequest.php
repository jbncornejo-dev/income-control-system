<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexEstadisticaIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_examen' => ['nullable', 'integer', 'exists:examen,id_examen'],
            'tipo_incidencia' => ['nullable', 'string', 'max:50'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_examen.exists' => 'El examen seleccionado no existe',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial',
        ];
    }
}
