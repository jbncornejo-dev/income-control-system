<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol?->nombre_rol, ['administrador', 'docente']);
    }

    protected function prepareForValidation(): void
    {
        foreach (['asignatura', 'fecha', 'hora_inicio'] as $campo) {
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
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'asignatura.string' => 'El nombre de la asignatura debe ser texto.',
            'asignatura.max' => 'El nombre de la asignatura no puede superar los 200 caracteres.',
            'fecha.date_format' => 'La fecha debe tener el formato YYYY-MM-DD.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM (24 horas).',
        ];
    }
}
