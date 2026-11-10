<?php

namespace App\Http\Requests;

use App\Models\Incidencia;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidenciaRequest extends FormRequest
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
            'id_examen' => ['required', 'integer', 'exists:examen,id_examen'],
            'id_estudiante' => ['nullable', 'integer', 'exists:estudiante,id_estudiante'],
            'tipo_incidencia' => ['required', 'string', 'in:' . implode(',', Incidencia::TIPOS)],
            'descripcion_motivo' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_examen.required' => 'Debe rellenar este campo para proceder',
            'tipo_incidencia.required' => 'Debe rellenar este campo para proceder',
            'tipo_incidencia.in' => 'El tipo de incidencia seleccionado no es válido',
            'descripcion_motivo.required' => 'Debe rellenar este campo para proceder',
        ];
    }

    protected function prepareForValidation(): void
    {
        $descripcion = $this->input('descripcion_motivo');

        $this->merge([
            'descripcion_motivo' => is_string($descripcion) ? trim($descripcion) : $descripcion,
            'id_estudiante' => $this->input('id_estudiante') ?: null,
        ]);
    }
}
