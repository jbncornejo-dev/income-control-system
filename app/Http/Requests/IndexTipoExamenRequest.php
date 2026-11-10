<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexTipoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'codigo'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => trim($valor) === '' ? null : trim($valor)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['nullable', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.string' => 'El filtro de nombre debe ser texto.',
            'nombre.max' => 'El filtro de nombre no puede superar los 100 caracteres.',
            'codigo.string' => 'El filtro de código debe ser texto.',
            'codigo.max' => 'El filtro de código no puede superar los 100 caracteres.',
        ];
    }
}
