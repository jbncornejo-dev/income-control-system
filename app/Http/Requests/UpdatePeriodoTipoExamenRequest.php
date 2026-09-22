<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePeriodoTipoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    /**
     * El front envía el plan completo del periodo, ya ordenado:
     * tipos: [{id_tipo_examen, orden}, ...]. Los tipos no incluidos se quitan.
     */
    public function rules(): array
    {
        return [
            'tipos' => ['required', 'array'],
            'tipos.*.id_tipo_examen' => ['required', 'integer', 'distinct', 'exists:tipo_examen,id_tipo_examen'],
            'tipos.*.orden' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipos.required' => 'Debe indicarse el plan de tipos de examen del periodo.',
            'tipos.array' => 'El plan de tipos debe enviarse como lista.',
            'tipos.*.id_tipo_examen.required' => 'Cada tipo del plan necesita su identificador.',
            'tipos.*.id_tipo_examen.integer' => 'El identificador del tipo debe ser un número.',
            'tipos.*.id_tipo_examen.distinct' => 'Un tipo no puede repetirse en el plan.',
            'tipos.*.id_tipo_examen.exists' => 'Uno de los tipos del plan no existe.',
            'tipos.*.orden.required' => 'Cada tipo del plan necesita un orden.',
            'tipos.*.orden.integer' => 'El orden debe ser un número entero.',
            'tipos.*.orden.min' => 'El orden no puede ser negativo.',
            'tipos.*.orden.max' => 'El orden no puede superar 99.',
        ];
    }
}
