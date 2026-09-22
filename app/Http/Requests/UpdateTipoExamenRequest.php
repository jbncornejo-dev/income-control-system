<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTipoExamenRequest extends StoreTipoExamenRequest
{
    public function rules(): array
    {
        $ignorado = $this->route('tipoExamen');

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tipo_examen', 'nombre')->ignore($ignorado),
            ],
            'codigo' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('tipo_examen', 'codigo')->ignore($ignorado),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
