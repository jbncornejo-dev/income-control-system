<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        foreach (['id_asignatura', 'nombre_asignatura'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => trim($valor) === '' ? null : trim($valor)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'id_asignatura' => ['nullable', 'integer', 'min:1'],
            'nombre_asignatura' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_asignatura.integer' => 'El ID de la asignatura debe ser un número entero.',
            'id_asignatura.min' => 'El ID de la asignatura debe ser mayor que cero.',
            'nombre_asignatura.string' => 'El nombre de la asignatura debe ser texto.',
            'nombre_asignatura.max' => 'El nombre de la asignatura no puede superar los 150 caracteres.',
        ];
    }
}
