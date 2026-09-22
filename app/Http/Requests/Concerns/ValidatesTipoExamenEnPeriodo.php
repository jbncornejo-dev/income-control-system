<?php

namespace App\Http\Requests\Concerns;

use App\Models\PeriodoTipoExamen;
use App\Models\TipoExamen;
use Illuminate\Validation\Validator;

trait ValidatesTipoExamenEnPeriodo
{
    /**
     * Valida que el tipo de examen elegido concuerde con el plan del periodo:
     *
     * - Si el periodo tiene plan (periodo_tipo_examen no vacío), el tipo es
     *   obligatorio y debe pertenecer al plan.
     * - Si el periodo no tiene plan, no debe enviarse tipo (no hay tipos aún).
     *
     * Con `$debeEstarActivo` (alta de examen o cambio de tipo) se rechazan los
     * tipos desactivados del catálogo; un examen conserva su tipo histórico
     * aunque el catálogo lo haya desactivado.
     */
    protected function validarTipoContraPlan(Validator $validator, int $idPeriodo, mixed $idTipoExamen, bool $debeEstarActivo = false): void
    {
        $idsPlan = PeriodoTipoExamen::query()
            ->where('id_periodo', $idPeriodo)
            ->pluck('id_tipo_examen')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($idsPlan === []) {
            if ($idTipoExamen !== null) {
                $validator->errors()->add(
                    'id_tipo_examen',
                    'El periodo aún no tiene un plan de tipos de examen definido.'
                );
            }

            return;
        }

        if ($idTipoExamen === null) {
            $validator->errors()->add(
                'id_tipo_examen',
                'Debe indicar el tipo de examen definido en el plan del periodo.'
            );

            return;
        }

        if (! in_array((int) $idTipoExamen, $idsPlan, true)) {
            $validator->errors()->add(
                'id_tipo_examen',
                'El tipo de examen seleccionado no está en el plan del periodo.'
            );

            return;
        }

        if ($debeEstarActivo
            && ! TipoExamen::query()
                ->where('id_tipo_examen', (int) $idTipoExamen)
                ->where('activo', true)
                ->exists()
        ) {
            $validator->errors()->add(
                'id_tipo_examen',
                'El tipo de examen está desactivado y no puede asignarse.'
            );
        }
    }
}
