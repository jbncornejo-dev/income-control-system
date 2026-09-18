<?php

namespace App\Http\Requests;

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
        ];
    }
}
