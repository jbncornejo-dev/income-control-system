<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['nullable', 'integer', 'exists:users,id'],
            'tabla_afectada' => ['nullable', 'string', 'max:50'],
            'accion' => ['nullable', 'string', 'max:10'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario.exists' => 'El usuario seleccionado no existe',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial',
        ];
    }
}
