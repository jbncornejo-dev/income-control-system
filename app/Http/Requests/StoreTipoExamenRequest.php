<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => strtolower(trim($this->input('codigo')))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipo_examen', 'nombre')],
            'codigo' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('tipo_examen', 'codigo')],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del tipo de examen es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un tipo de examen con ese nombre.',
            'codigo.required' => 'El código del tipo de examen es obligatorio.',
            'codigo.max' => 'El código no puede superar los 100 caracteres.',
            'codigo.regex' => 'El código solo puede contener minúsculas, números y guiones bajos.',
            'codigo.unique' => 'Ya existe un tipo de examen con ese código.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
