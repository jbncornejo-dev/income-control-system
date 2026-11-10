<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTipoExamenEnPeriodo;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Periodo;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExamenRequest extends FormRequest
{
    use ValidatesTipoExamenEnPeriodo;

    public function authorize(): bool
    {
        $rol = $this->user()?->rol?->nombre_rol;

        if ($rol === 'administrador') {
            return true;
        }

        // El docente solo puede editar exámenes cuyos grupos le pertenecen por
        // completo (un examen compartido con otros docentes lo gestiona el admin).
        if ($rol === 'docente') {
            $examen = $this->route('examen');

            return $examen instanceof Examen
                && $examen->perteneceIntegramenteA($this->user()->id);
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['fecha', 'hora_inicio', 'normas_generales'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => trim($valor) === '' ? null : trim($valor)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['sometimes', 'integer', 'exists:asignatura,id_asignatura'],
            'id_periodo' => ['sometimes', 'integer', 'exists:periodo,id_periodo'],
            'id_tipo_examen' => ['sometimes', 'nullable', 'integer', 'exists:tipo_examen,id_tipo_examen'],
            'fecha' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
            'duracion_minutos' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'normas_generales' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // `id_grupos`/`id_ambientes` solo se tocan si vienen en el PATCH;
            // un `null` explícito se rechaza (no se puede vaciar el examen).
            'id_grupos' => ['sometimes', 'array', 'min:1'],
            'id_grupos.*' => ['required', 'integer', 'distinct', 'exists:grupo,id_grupo'],
            'id_ambientes' => ['sometimes', 'array', 'min:1'],
            'id_ambientes.*' => ['required', 'integer', 'distinct', 'exists:ambiente,id_ambiente'],
        ];
    }

    /**
     * Solo tiene sentido validar "fecha futura" cuando llegan junto fecha y hora de inicio.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // authorize() ya garantiza que el examen pertenece a una asignatura del docente;
            // aquí se evita que lo cambie a una asignatura que no dicta.
            if ($this->user()?->rol?->nombre_rol === 'docente') {
                $examen = $this->route('examen');
                $idAsignatura = $this->input('id_asignatura') ?? $examen->id_asignatura;

                $dicta = Grupo::query()
                    ->where('id_usuario', $this->user()->id)
                    ->where('id_asignatura', $idAsignatura)
                    ->exists();

                if (! $dicta) {
                    $validator->errors()->add(
                        'id_asignatura',
                        'Solo puedes editar exámenes de las asignaturas que dictas.'
                    );
                }
            }

            // La estructura (asignatura, periodo, grupos) se revalida cuando se
            // modifica cualquiera de sus componentes: los grupos efectivos (los
            // enviados o los ya vinculados al examen) deben seguir perteneciendo
            // a la asignatura y a la gestión del periodo efectivos.
            if ($this->exists('id_grupos') || $this->exists('id_asignatura') || $this->exists('id_periodo')) {
                $examen = $this->route('examen');
                $idAsignatura = $this->input('id_asignatura') ?? $examen->id_asignatura;
                $idPeriodo = $this->input('id_periodo') ?? $examen->id_periodo;
                $idGrupos = $this->input('id_grupos') ?? $examen->grupos()->pluck('grupo.id_grupo')->all();

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

                // La gestión de los grupos debe coincidir con la del periodo.
                $gestionPeriodo = Periodo::find((int) $idPeriodo)?->gestion;

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

                if ($this->user()?->rol?->nombre_rol === 'docente') {
                    $gruposDelDocente = Grupo::query()
                        ->whereIn('id_grupo', $idGrupos)
                        ->where('id_usuario', $this->user()->id)
                        ->count();

                    if ($gruposDelDocente !== count(array_unique($idGrupos))) {
                        $validator->errors()->add(
                            'id_grupos',
                            'Solo puedes asignar al examen los grupos que dictas.'
                        );
                    }
                }
            }

            // El tipo de examen debe venir del plan del periodo efectivo. Se
            // valida tanto cuando se cambia el tipo como cuando se cambia el
            // periodo (para no mover un examen a un plan que no lo contiene).
            if ($this->exists('id_tipo_examen') || $this->exists('id_periodo')) {
                $examen = $this->route('examen');
                $idPeriodo = $this->input('id_periodo') ?? $examen->id_periodo;
                $idTipo = $this->exists('id_tipo_examen')
                    ? $this->input('id_tipo_examen')
                    : $examen->id_tipo_examen;

                // Los tipos desactivados no pueden asignarse de nuevo, pero el
                // examen conserva su tipo histórico aunque el catálogo lo haya
                // desactivado (no se revalida lo que no cambió).
                $cambioDeTipo = $this->exists('id_tipo_examen')
                    && (int) $this->input('id_tipo_examen') !== (int) $examen->id_tipo_examen;

                $this->validarTipoContraPlan($validator, (int) $idPeriodo, $idTipo, $cambioDeTipo);
            }

            $fecha = $this->input('fecha');
            $hora = $this->input('hora_inicio');

            // Si no vienen ambos, no hay horario completo que validar.
            if ($fecha === null || $hora === null) {
                return;
            }

            $inicio = Carbon::createFromFormat('Y-m-d H:i', $fecha.' '.$hora);

            if ($inicio->lessThanOrEqualTo(now())) {
                $validator->errors()->add(
                    'hora_inicio',
                    'La fecha y hora de inicio deben estar en el futuro.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_asignatura.integer' => 'La asignatura es inválida.',
            'id_asignatura.exists' => 'La asignatura seleccionada no existe.',
            'id_periodo.integer' => 'El periodo es inválido.',
            'id_periodo.exists' => 'El periodo seleccionado no existe.',
            'id_tipo_examen.integer' => 'El tipo de examen es inválido.',
            'id_tipo_examen.exists' => 'El tipo de examen seleccionado no existe.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion_minutos.integer' => 'La duración debe ser un número entero.',
            'duracion_minutos.min' => 'La duración debe ser de al menos 1 minuto.',
            'duracion_minutos.max' => 'La duración no puede superar 720 minutos.',
            'id_grupos.min' => 'Debe seleccionar al menos un grupo.',
            'id_grupos.array' => 'Los grupos deben enviarse como lista.',
            'id_grupos.*.exists' => 'Uno de los grupos seleccionados no existe.',
            'id_grupos.*.distinct' => 'Un grupo no puede seleccionarse más de una vez.',
            'id_ambientes.min' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.array' => 'Los ambientes deben enviarse como lista.',
            'id_ambientes.*.exists' => 'Uno de los ambientes seleccionados no existe.',
            'id_ambientes.*.distinct' => 'Un ambiente no puede seleccionarse más de una vez.',
        ];
    }
}
