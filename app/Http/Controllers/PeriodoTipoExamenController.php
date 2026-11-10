<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePeriodoTipoExamenRequest;
use App\Models\Periodo;

class PeriodoTipoExamenController extends Controller
{
    /**
     * Reemplaza el plan de tipos de un periodo (periodo_tipo_examen). El front
     * envía la lista completa del plan con su orden; los tipos no incluidos se
     * quitan del plan de esa gestión.
     */
    public function sync(UpdatePeriodoTipoExamenRequest $request, Periodo $periodo)
    {
        $plan = collect($request->validated()['tipos'])
            ->keyBy('id_tipo_examen')
            ->map(fn (array $fila) => ['orden' => $fila['orden']])
            ->all();

        $periodo->tiposExamen()->sync($plan);

        return back()->with('success', 'Plan de tipos de examen actualizado correctamente.');
    }
}
