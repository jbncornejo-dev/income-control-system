<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAmbienteRequest extends StoreAmbienteRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['nombre_ambiente'] = [
            'required', 'string', 'max:100',
            Rule::unique('ambiente', 'nombre_ambiente')->ignore($this->route('ambiente')),
        ];

        return $rules;
    }
}
