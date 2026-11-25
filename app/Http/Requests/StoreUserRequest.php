<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rol;
use App\Models\Estudiante;
use App\Support\AdminCredentials;

class StoreUserRequest extends FormRequest
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
        $rol = Rol::find($this->integer('id_rol'));
        $esRolEstudiante = $rol?->nombre_rol === 'estudiante';
        $esAdmin = $rol?->nombre_rol === 'administrador';
        $usaClaveSegura = $rol?->requiereClaveSegura() ?? false;

        return [
            'id_rol' => ['required', 'integer', Rule::exists('rol', 'id_rol')],
            'nombres' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'codigo_universitario' => [
                Rule::prohibitedIf($esAdmin),
                Rule::requiredIf(! $esAdmin), 'nullable', 'string', 'max:20', 'regex:'.Estudiante::REGEX_CODIGO_SIS,
                'unique:users,username',
                Rule::when($esRolEstudiante, ['unique:estudiante,codigo_universitario']),
            ],
            'documento_identidad' => [
                'required', 'string', 'max:20', 'regex:'.Estudiante::REGEX_DOCUMENTO_CI,
                'unique:users,documento_identidad',
                Rule::when($esRolEstudiante, ['unique:estudiante,documento_identidad']),
            ],
            'email' => [
                'nullable', 'email', 'max:255', 'unique:users,email',
                Rule::when($esRolEstudiante, ['unique:estudiante,email']),
            ],
            'username' => $esAdmin
                ? ['required', 'string', AdminCredentials::USERNAME, 'not_in:admin,administrador', 'unique:users,username']
                : ['prohibited'],
            'password' => $usaClaveSegura
                ? ['required', 'string', 'max:72', 'confirmed', AdminCredentials::password()]
                : ['prohibited'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'El correo ya está registrado.',
            'username.required' => 'Escribe un nombre de usuario individual.',
            'username.regex' => 'Usa de 6 a 40 caracteres, con letras minúsculas y, si lo deseas, números, puntos, guiones o guiones bajos.',
            'username.not_in' => 'Selecciona un nombre individual para el administrador.',
            'username.unique' => 'El nombre de usuario ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'codigo_universitario.unique' => 'El código universitario ya está registrado.',
            'documento_identidad.unique' => 'El CI ya está registrado.',
            'codigo_universitario.regex' => 'El código universitario debe tener 9 dígitos e iniciar con el año de ingreso.',
            'documento_identidad.regex' => 'El CI debe tener entre 6 y 8 dígitos.',
        ];
    }
}
