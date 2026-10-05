<?php

namespace App\Http\Requests;

use App\Models\Grupo;
use App\Models\Inscripcion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    public function rules(): array
    {
        return [
            'id_estudiante' => ['required', 'integer', 'exists:estudiante,id_estudiante'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_estudiante.required' => 'Debe seleccionar un estudiante',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $grupo = $this->route('grupo');
                $idEstudiante = $this->input('id_estudiante');

                if (! $grupo instanceof Grupo || ! $idEstudiante) {
                    return;
                }

                if (Inscripcion::where('id_grupo', $grupo->id_grupo)->where('id_estudiante', $idEstudiante)->exists()) {
                    $validator->errors()->add('id_estudiante', 'El estudiante ya esta inscrito en este grupo');

                    return;
                }

                $enOtroGrupo = Inscripcion::where('id_estudiante', $idEstudiante)
                    ->whereHas('grupo', fn ($q) => $q
                        ->where('id_asignatura', $grupo->id_asignatura)
                        ->where('gestion', $grupo->gestion))
                    ->exists();

                if ($enOtroGrupo) {
                    $validator->errors()->add('id_estudiante', 'El estudiante ya esta inscrito en otro grupo de esta asignatura para esta gestion');
                }
            },
        ];
    }
}
