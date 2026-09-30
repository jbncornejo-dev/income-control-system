<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePersonalControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->rol?->nombre_rol, ['administrador', 'docente'], true);
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
            'id_usuario.required' => 'Debe seleccionar un usuario para asignar',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $usuario = User::with('rol')->find($this->input('id_usuario'));

                if ($usuario && $usuario->rol?->nombre_rol !== 'personal de control de ingreso') {
                    $validator->errors()->add('id_usuario', 'El usuario seleccionado no es personal de control de ingreso');
                }
            },
        ];
    }
}
