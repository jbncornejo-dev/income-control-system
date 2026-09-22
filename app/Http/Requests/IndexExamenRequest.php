<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol?->nombre_rol, ['administrador', 'docente']);
    }

    protected function prepareForValidation(): void
    {
        foreach (['asignatura', 'fecha', 'hora_inicio', 'estado', 'id_tipo_examen', 'compartido'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => trim($valor) === '' ? null : trim($valor)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'asignatura' => ['nullable', 'string', 'max:200'],
            'id_periodo' => ['nullable', 'integer', 'exists:periodo,id_periodo'],
            'id_tipo_examen' => ['nullable', 'integer', 'exists:tipo_examen,id_tipo_examen'],
            // "1" = solo exámenes compartidos entre dos o más docentes.
            'compartido' => ['nullable', Rule::in(['0', '1'])],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            // Buckets de gestión (cancelado/anulado/suspendido) y del ciclo (programado/en_curso/finalizado).
            'estado' => ['nullable', Rule::in(['programado', 'en_curso', 'finalizado', 'cancelado', 'anulado', 'suspendido'])],
        ];
    }

    public function messages(): array
    {
        return [
            'asignatura.string' => 'El nombre de la asignatura debe ser texto.',
            'asignatura.max' => 'El nombre de la asignatura no puede superar los 200 caracteres.',
            'id_periodo.exists' => 'El periodo seleccionado no existe.',
            'id_tipo_examen.exists' => 'El tipo de examen seleccionado no existe.',
            'compartido.in' => 'El filtro de exámenes compartidos no es válido.',
            'fecha.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM (24 horas).',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
