<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTipoExamenEnPeriodo;
use App\Models\Grupo;
use App\Models\Periodo;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExamenRequest extends FormRequest
{
    use ValidatesTipoExamenEnPeriodo;

    public function authorize(): bool
    {
        return in_array($this->user()?->rol?->nombre_rol, ['administrador', 'docente'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha' => trim((string) $this->fecha),
            'hora_inicio' => trim((string) $this->hora_inicio),
            'normas_generales' => $this->normas_generales !== null
                ? trim((string) $this->normas_generales)
                : null,
        ]);

        if ($this->normas_generales === '') {
            $this->merge(['normas_generales' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['required', 'integer', 'exists:asignatura,id_asignatura'],
            'id_periodo' => ['required', 'integer', 'exists:periodo,id_periodo'],
            'id_tipo_examen' => ['nullable', 'integer', 'exists:tipo_examen,id_tipo_examen'],
            'id_grupos' => ['required', 'array', 'min:1'],
            'id_grupos.*' => ['required', 'integer', 'distinct', 'exists:grupo,id_grupo'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:720'],
            'normas_generales' => ['nullable', 'string', 'max:5000'],
            'id_ambientes' => ['required', 'array', 'min:1'],
            'id_ambientes.*' => ['required', 'integer', 'distinct', 'exists:ambiente,id_ambiente'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $idAsignatura = $this->input('id_asignatura');
            $idGrupos = $this->input('id_grupos') ?? [];
            $esDocente = $this->user()?->rol?->nombre_rol === 'docente';

            // Los grupos deben pertenecer a la asignatura del examen.
            $gruposDeLaAsignatura = Grupo::query()
                ->whereIn('id_grupo', $idGrupos)
                ->where('id_asignatura', $idAsignatura)
                ->count();

            if ($gruposDeLaAsignatura !== count(array_unique($idGrupos))) {
                $validator->errors()->add(
                    'id_grupos',
                    'Todos los grupos seleccionados deben pertenecer a la asignatura del examen.'
                );
            }

            // El docente solo puede registrar exámenes de los grupos que dicta.
            if ($esDocente) {
                $gruposDelDocente = Grupo::query()
                    ->whereIn('id_grupo', $idGrupos)
                    ->where('id_usuario', $this->user()->id)
                    ->count();

                if ($gruposDelDocente !== count(array_unique($idGrupos))) {
                    $validator->errors()->add(
                        'id_grupos',
                        'Solo puedes registrar exámenes para los grupos que dictas.'
                    );
                }
            }

            // La gestión de los grupos debe coincidir con la del periodo: un
            // examen de 2026 no puede rendirse por grupos de otra gestión.
            $gestionPeriodo = Periodo::find((int) $this->input('id_periodo'))?->gestion;

            if ($gestionPeriodo !== null) {
                $gruposDeLaGestion = Grupo::query()
                    ->whereIn('id_grupo', $idGrupos)
                    ->where('gestion', $gestionPeriodo)
                    ->count();

                if ($gruposDeLaGestion !== count(array_unique($idGrupos))) {
                    $validator->errors()->add(
                        'id_grupos',
                        'Los grupos seleccionados deben pertenecer a la misma gestión que el periodo del examen.'
                    );
                }
            }

            // El tipo de examen debe venir del plan definido para este periodo.
            $this->validarTipoContraPlan($validator, (int) $this->input('id_periodo'), $this->input('id_tipo_examen'), true);

            $inicio = Carbon::createFromFormat(
                'Y-m-d H:i',
                $this->input('fecha').' '.$this->input('hora_inicio')
            );

            if ($inicio->lessThanOrEqualTo(now())) {
                $validator->errors()->add(
                    'hora_inicio',
                    'La fecha y hora de inicio deben estar en el futuro.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_asignatura.required' => 'La asignatura es obligatoria.',
            'id_asignatura.exists' => 'La asignatura seleccionada no existe.',
            'id_periodo.required' => 'El periodo es obligatorio.',
            'id_periodo.exists' => 'El periodo seleccionado no existe.',
            'id_tipo_examen.integer' => 'El tipo de examen es inválido.',
            'id_tipo_examen.exists' => 'El tipo de examen seleccionado no existe.',
            'fecha.required' => 'La fecha del examen es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.after_or_equal' => 'La fecha del examen no puede estar en el pasado.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion_minutos.required' => 'La duración es obligatoria.',
            'duracion_minutos.min' => 'La duración debe ser de al menos 1 minuto.',
            'duracion_minutos.max' => 'La duración no puede superar 720 minutos.',
            'id_grupos.required' => 'Debe seleccionar al menos un grupo.',
            'id_grupos.array' => 'Los grupos deben enviarse como lista.',
            'id_grupos.min' => 'Debe seleccionar al menos un grupo.',
            'id_grupos.*.exists' => 'Uno de los grupos seleccionados no existe.',
            'id_grupos.*.distinct' => 'Un grupo no puede seleccionarse más de una vez.',
            'id_ambientes.required' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.min' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.*.exists' => 'Uno de los ambientes seleccionados no existe.',
            'id_ambientes.*.distinct' => 'Un ambiente no puede seleccionarse más de una vez.',
        ];
    }
}
