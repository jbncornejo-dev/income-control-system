<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rolesPermitidos = ['administrador', 'docente', 'personal de control de ingreso'];

        return in_array($this->user()?->rol?->nombre_rol, $rolesPermitidos, true);
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        if (is_string($search)) {
            $this->merge(['search' => trim($search) === '' ? null : trim($search)]);
        }
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser texto.',
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
        ];
    }
}
