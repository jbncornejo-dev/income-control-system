<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    public function rules(): array
    {
        return [
            'id_asignatura' => ['nullable', 'integer', 'exists:asignatura,id_asignatura'],
            'gestion' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return array_filter(parent::validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
