<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateEstudianteRequest extends StoreEstudianteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'codigo_qr' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('estudiante', 'codigo_qr')
                    ->ignore($this->route('estudiante')),
            ],
        ];
    }
}
