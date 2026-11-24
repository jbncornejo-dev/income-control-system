<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Asignatura;

class StoreAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('nombre_asignatura'))) {
            return;
        }

        $this->merge([
            'nombre_asignatura' => trim(preg_replace('/\s+/u', ' ', $this->input('nombre_asignatura'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre_asignatura' => [
                'bail',
                'required',
                'string',
                'max:150',
                function (string $attribute, string $value, \Closure $fail) {
                    $asignatura = $this->route('asignatura');
                    $query = Asignatura::whereRaw('normalizar_nombre_asignatura(nombre_asignatura) = normalizar_nombre_asignatura(?)', [$value]);
                    if ($asignatura && (clone $query)->whereKey($asignatura->getKey())->exists()) {
                        return;
                    }
                    if ($query->exists()) {
                        $fail('Ya existe una asignatura con ese nombre');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_asignatura.required' => 'El nombre de la asignatura es obligatorio.',
            'nombre_asignatura.string' => 'El nombre de la asignatura debe ser texto.',
            'nombre_asignatura.max' => 'El nombre de la asignatura no puede superar los 150 caracteres.',
            'nombre_asignatura.unique' => 'Ya existe una asignatura con ese nombre',
        ];
    }
}
