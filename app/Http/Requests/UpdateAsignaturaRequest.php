<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAsignaturaRequest extends StoreAsignaturaRequest
{
    public function rules(): array
    {
        return [
            'nombre_asignatura' => [
                'required',
                'string',
                'max:150',
                Rule::unique('asignatura', 'nombre_asignatura')
                    ->ignore($this->route('asignatura')),
            ],
        ];
    }
}
