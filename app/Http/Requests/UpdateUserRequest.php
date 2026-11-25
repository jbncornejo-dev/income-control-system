<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Support\AdminCredentials;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $rol = Rol::find($this->integer('id_rol', $usuario->id_rol));
        $esAdmin = $rol?->nombre_rol === 'administrador';
        $promocion = $esAdmin && $usuario->rol?->nombre_rol !== 'administrador';

        $accesoReportes = $rol?->permisos !== null && $usuario->rol?->permisos === null;

        return [
            'id_rol' => ['sometimes', 'required', 'integer', Rule::exists('rol', 'id_rol')],
            'username' => [
                Rule::requiredIf($promocion), Rule::prohibitedIf(! $esAdmin), 'nullable', 'string',
                AdminCredentials::USERNAME, 'not_in:admin,administrador', Rule::unique('users')->ignore($usuario),
            ],
            'password' => [
                Rule::requiredIf($promocion || $accesoReportes), 'nullable', 'string', 'max:72', 'confirmed',
                $rol?->requiereClaveSegura() ? AdminCredentials::password() : 'min:8',
            ],
        ];
    }
}
