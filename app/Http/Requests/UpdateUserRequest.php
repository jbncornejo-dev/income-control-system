<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rol;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedRoles = Rol::whereIn('nombre_rol', [
            'administrador',
            'docente',
            'personal de control de ingreso',
            'estudiante',
        ])->pluck('id_rol')->toArray();

        // Determinar el ID del usuario de la ruta (puede ser 'user' o 'usuario')
        $user = $this->route('user') ?? $this->route('usuario');
        $userId = is_object($user) ? $user->id : $user;

        return [
            'id_rol' => ['sometimes', 'required', 'integer', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'El nombre de usuario o email ya está registrado',
            'username.unique' => 'El nombre de usuario o email ya está registrado',
        ];
    }
}
