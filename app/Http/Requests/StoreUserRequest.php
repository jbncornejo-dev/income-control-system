<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Rol;
use App\Models\Estudiante;

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
        $allowedRoles = Rol::whereIn('nombre_rol', [
            'administrador',
            'docente',
            'personal de control de ingreso',
            'estudiante',
        ])->pluck('id_rol')->toArray();

        return [
            'id_rol' => ['required', 'integer', Rule::in($allowedRoles)],
            'nombres' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'codigo_universitario' => [
                'required', 'string', 'max:20', 'regex:'.Estudiante::REGEX_CODIGO_SIS,
                'unique:users,username',
                Rule::when($this->esRolEstudiante(), ['unique:estudiante,codigo_universitario']),
            ],
            'documento_identidad' => [
                'required', 'string', 'max:20', 'regex:'.Estudiante::REGEX_DOCUMENTO_CI,
                'unique:users,documento_identidad',
                Rule::when($this->esRolEstudiante(), ['unique:estudiante,documento_identidad']),
            ],
            'email' => [
                'nullable', 'email', 'max:255', 'unique:users,email',
                Rule::when($this->esRolEstudiante(), ['unique:estudiante,email']),
            ],
            'password' => ['prohibited'],
        ];
    }

    private function esRolEstudiante(): bool
    {
        $rolEstudiante = Rol::where('nombre_rol', 'estudiante')->value('id_rol');

        return (int) $this->input('id_rol') === (int) $rolEstudiante;
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'El correo ya está registrado.',
            'codigo_universitario.unique' => 'El código universitario ya está registrado.',
            'documento_identidad.unique' => 'El CI ya está registrado.',
            'codigo_universitario.regex' => 'El código universitario debe tener 9 dígitos e iniciar con el año de ingreso.',
            'documento_identidad.regex' => 'El CI debe tener entre 6 y 8 dígitos.',
        ];
    }
}
