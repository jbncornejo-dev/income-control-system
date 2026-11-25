<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisponibilidadExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rol = $this->user()?->rol?->nombre_rol;

        return in_array($rol, ['administrador', 'docente'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:720'],
            // En edición, el propio examen no debe considerarse ocupado.
            'excluir_examen' => ['sometimes', 'integer', 'exists:examen,id_examen'],
        ];
    }
}
