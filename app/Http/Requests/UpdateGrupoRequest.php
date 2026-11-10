<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario.required' => 'Debe seleccionar un docente',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $docente = User::with('rol')->find($this->input('id_usuario'));

                if ($docente && $docente->rol?->nombre_rol !== 'docente') {
                    $validator->errors()->add('id_usuario', 'El usuario seleccionado no es docente');
                }
            },
        ];
    }
}
