<?php

namespace App\Http\Requests;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateEstudianteRequest extends StoreEstudianteRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->offsetUnset('codigo_qr');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Extraemos el ID, cubriendo el caso de que la ruta inyecte el modelo completo o solo el ID numérico
        $estudiante = $this->route('estudiante');
        $id = is_object($estudiante) ? $estudiante->id_estudiante : $estudiante;

        // ID de la cuenta vinculada al estudiante (si la tiene): al validar
        // username/email contra users se ignora su propia cuenta.
        $cuentaId = is_object($estudiante) ? $estudiante->user?->id : null;

        return [
            'nombres' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'apellidos' => ['required', 'string', 'max:100', 'regex:'.Estudiante::REGEX_NOMBRES],
            'codigo_universitario' => [
                'required',
                'string',
                'max:20',
                'regex:'.Estudiante::REGEX_CODIGO_SIS,
                Rule::unique('estudiante', 'codigo_universitario')->ignore($id, 'id_estudiante'),
                function ($attribute, $value, $fail) use ($cuentaId) {
                    $query = User::query()->where('username', $value);

                    if ($cuentaId !== null) {
                        $query->where('id', '!=', $cuentaId);
                    }

                    if ($query->exists()) {
                        $fail('El código universitario ya está en uso por otra cuenta de acceso.');
                    }
                },
            ],
            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                'regex:'.Estudiante::REGEX_DOCUMENTO_CI,
                Rule::unique('estudiante', 'documento_identidad')->ignore($id, 'id_estudiante'),
            ],
            'codigo_qr' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('estudiante', 'codigo_qr')->ignore($id, 'id_estudiante'),
            ],
            'email' => [
                'nullable',
                'string',
                'max:255',
                'regex:'.Estudiante::REGEX_EMAIL_UMSS,
                Rule::unique('estudiante', 'email')->ignore($id, 'id_estudiante'),
                function ($attribute, $value, $fail) use ($cuentaId) {
                    $query = User::query()->where('email', $value);

                    if ($cuentaId !== null) {
                        $query->where('id', '!=', $cuentaId);
                    }

                    if ($query->exists()) {
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
        return array_merge(parent::messages(), [
            'codigo_universitario.unique' => 'Este código universitario ya pertenece a otro estudiante.',
            'documento_identidad.unique' => 'Este documento de identidad ya pertenece a otro estudiante.',
            'codigo_qr.unique' => 'Este código QR ya está en uso.',
            'email.unique' => 'Este correo electrónico ya está en uso.',
        ]);
    }
}
