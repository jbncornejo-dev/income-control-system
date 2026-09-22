<?php

namespace App\Http\Requests\Concerns;

use App\Models\PeriodoTipoExamen;
use Illuminate\Validation\Validator;

trait ValidatesTipoExamenEnPeriodo
{
    /**
     * Valida que el tipo de examen elegido concuerde con el plan del periodo:
     *
     * - Si el periodo tiene plan (periodo_tipo_examen no vacío), el tipo es
     *   obligatorio y debe pertenecer al plan.
     * - Si el periodo no tiene plan, no debe enviarse tipo (no hay tipos aún).
     */
    protected function validarTipoContraPlan(Validator $validator, int $idPeriodo, mixed $idTipoExamen): void
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
        }
    }
}
