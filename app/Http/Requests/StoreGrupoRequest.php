<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    public function rules(): array
    {
        return [
            'id_asignatura' => ['required', 'integer', 'exists:asignatura,id_asignatura'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'gestion' => ['required', 'string', 'max:20', 'regex:/^\d{4}$/'],
            'nombre_grupo' => [
                'required', 'string', 'max:50',
                Rule::unique('grupo', 'nombre_grupo')
                    ->where('id_asignatura', $this->input('id_asignatura'))
                    ->where('gestion', $this->input('gestion')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_asignatura.required' => 'Debe seleccionar una asignatura',
            'id_usuario.required' => 'Debe seleccionar un docente',
            'gestion.required' => 'Debe indicar la gestion',
            'gestion.regex' => 'La gestion debe ser un ano de cuatro digitos, por ejemplo 2026',
            'nombre_grupo.required' => 'Debe indicar el nombre del grupo',
            'nombre_grupo.unique' => 'Ya existe un grupo con ese nombre para esa asignatura en esa gestion',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $docente = User::with('rol')->find($this->input('id_usuario'));

                if ($docente && $docente->rol?->nombre_rol !== 'docente') {
                    $validator->errors()->add('id_usuario', 'El usuario seleccionado no es docente');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_grupo' => is_string($this->input('nombre_grupo')) ? trim($this->input('nombre_grupo')) : $this->input('nombre_grupo'),
            'gestion' => is_string($this->input('gestion')) ? trim($this->input('gestion')) : $this->input('gestion'),
        ]);
    }
}
