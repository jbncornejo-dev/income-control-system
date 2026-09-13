<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('nombre_asignatura'))) {
            return;
        }

        $this->merge([
            'nombre_asignatura' => trim($this->input('nombre_asignatura')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre_asignatura' => [
                'required',
                'string',
                'max:150',
                Rule::unique('asignatura', 'nombre_asignatura'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_asignatura.required' => 'El nombre de la asignatura es obligatorio.',
            'nombre_asignatura.string' => 'El nombre de la asignatura debe ser texto.',
            'nombre_asignatura.max' => 'El nombre de la asignatura no puede superar los 150 caracteres.',
            'nombre_asignatura.unique' => 'Ya existe una asignatura con ese nombre',
        ];
    }
}
