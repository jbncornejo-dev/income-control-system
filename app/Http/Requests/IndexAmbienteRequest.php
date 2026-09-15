<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre_ambiente');
        if (is_string($nombre)) {
            $this->merge(['nombre_ambiente' => trim($nombre) === '' ? null : trim($nombre)]);
        }
    }

    public function rules(): array
    {
        return ['nombre_ambiente' => ['nullable', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return [
            'nombre_ambiente.string' => 'El nombre del ambiente debe ser texto.',
            'nombre_ambiente.max' => 'El nombre del ambiente no puede superar los 100 caracteres.',
        ];
    }
}
