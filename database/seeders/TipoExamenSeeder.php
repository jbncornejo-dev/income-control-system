<?php

namespace Database\Seeders;

use App\Models\Periodo;
use App\Models\PeriodoTipoExamen;
use App\Models\TipoExamen;
use Illuminate\Database\Seeder;

class TipoExamenSeeder extends Seeder
{
    /**
     * Catálogo de tipos de examen por defecto y su plan de evaluación para los
     * periodos del año en curso, en el orden en que aparecerán en el selector
     * al registrar un examen. Si el administrador ya configuró el plan de un
     * periodo, no se pisa su configuración.
     */
    public function run(): void
    {
        $tipos = [
            'primer_parcial' => 'Primer Parcial',
            'segundo_parcial' => 'Segundo Parcial',
            'examen_final' => 'Examen Final',
            'segunda_instancia' => 'Segunda Instancia',
            'mesa_examen' => 'Mesa de Examen',
        ];

        $tiposCreados = [];

        foreach ($tipos as $codigo => $nombre) {
            $tiposCreados[] = TipoExamen::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true]
            );
        }

        // Plan por defecto: los periodos del año en curso reciben el catálogo
        // completo en orden, para que el selector de tipo de examen muestre las
        // opciones. Solo se siembra si el periodo aún no tiene plan.
        $periodos = Periodo::query()
            ->where('gestion', (string) now()->year)
            ->get();

        foreach ($periodos as $periodo) {
            if ($periodo->tiposExamen()->exists()) {
                continue;
            }

            foreach ($tiposCreados as $orden => $tipo) {
                PeriodoTipoExamen::updateOrCreate(
                    ['id_periodo' => $periodo->id_periodo, 'id_tipo_examen' => $tipo->id_tipo_examen],
                    ['orden' => $orden + 1]
                );
            }
        }
    }
}
