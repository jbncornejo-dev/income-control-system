<?php

namespace App\Http\Requests;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => trim((string) $this->nombres),
            'apellidos' => trim((string) $this->apellidos),
            'codigo_universitario' => trim((string) $this->codigo_universitario),
            'documento_identidad' => trim((string) $this->documento_identidad),
            'codigo_qr' => $this->codigo_qr !== null ? trim((string) $this->codigo_qr) : null,
            'email' => $this->email !== null ? strtolower(trim((string) $this->email)) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo_universitario' => [
                'required',
                'string',
                'max:20',
                'regex:'.Estudiante::REGEX_CODIGO_SIS,
                'unique:estudiante,codigo_universitario',
                // El código es el username de la cuenta del estudiante: no debe
                // chocar con ninguna cuenta existente (docentes, control, etc.).
                function ($attribute, $value, $fail) {
                    if (User::query()->where('username', $value)->exists()) {
                        $fail('El código universitario ya está en uso por otra cuenta de acceso.');
                    }
                },
            ],
            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                'regex:'.Estudiante::REGEX_DOCUMENTO_CI,
                'unique:estudiante,documento_identidad',
            ],
            'nombres' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'email' => [
                'nullable',
                'string',
                'max:255',
                'regex:'.Estudiante::REGEX_EMAIL_UMSS,
                'unique:estudiante,email',
                // El correo institucional no puede pertenecer a otra cuenta de
                // acceso, o el login por correo resolvería al usuario equivocado.
                function ($attribute, $value, $fail) {
                    if (User::query()->where('email', $value)->exists()) {
                        $fail('El correo ya está en uso por otra cuenta de acceso.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_universitario.required' => 'El código universitario es obligatorio.',
            'codigo_universitario.regex' => 'El código universitario debe tener 9 dígitos e iniciar con el año de ingreso.',
            'codigo_universitario.unique' => 'El código universitario ya está registrado.',
            'documento_identidad.required' => 'El documento de identidad es obligatorio.',
            'documento_identidad.regex' => 'El documento de identidad debe tener entre 6 y 8 dígitos.',
            'documento_identidad.unique' => 'El documento de identidad ya está registrado.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.regex' => 'Los nombres solo pueden contener letras, espacios, apóstrofes y guiones.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras, espacios, apóstrofes y guiones.',
            'email.regex' => 'El correo debe tener un formato válido.',
            'email.unique' => 'El correo ya está registrado.',
        ];
    }
}
