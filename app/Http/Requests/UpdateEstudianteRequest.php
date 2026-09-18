<?php

namespace App\Http\Requests;



class UpdateEstudianteRequest extends StoreEstudianteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $estudiante = $this->route('estudiante');
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'codigo_universitario' => [
                'required',
                'string',
                'max:20',
                // Verifica que sea único en la tabla 'estudiante', ignorando el registro actual
                Rule::unique('estudiante', 'codigo_universitario')->ignore($estudiante),
            ],
            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                Rule::unique('estudiante', 'documento_identidad')->ignore($estudiante),
            ],
            'codigo_qr' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('estudiante', 'codigo_qr')->ignore($estudiante),
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
        ]);
    }
}
