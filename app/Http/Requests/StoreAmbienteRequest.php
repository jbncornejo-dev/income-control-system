<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre_ambiente'))) {
            $this->merge(['nombre_ambiente' => trim($this->input('nombre_ambiente'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre_ambiente' => ['required', 'string', 'max:100', Rule::unique('ambiente', 'nombre_ambiente')],
            // PostgreSQL almacena esta columna como integer de 32 bits con signo.
            'capacidad' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_ambiente.required' => 'El nombre del ambiente es obligatorio.',
            'nombre_ambiente.string' => 'El nombre del ambiente debe ser texto.',
            'nombre_ambiente.max' => 'El nombre del ambiente no puede superar los 100 caracteres.',
            'nombre_ambiente.unique' => 'Ya existe un ambiente con ese nombre',
            'capacidad.required' => 'La capacidad es obligatoria.',
            'capacidad.integer' => 'La capacidad debe ser un número entero.',
            'capacidad.min' => 'La capacidad debe ser mayor que cero.',
            'capacidad.max' => 'La capacidad no puede superar 2147483647.',
        ];
    }
}
