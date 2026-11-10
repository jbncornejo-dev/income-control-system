<?php

namespace Database\Seeders;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\ExamenQr;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\RegistroIngreso;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoOperativoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();
        $control = User::where('username', 'control')->first();

        if (! $admin || ! $control) {
            return;
        }

        $examenes = Examen::query()
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        foreach ($examenes as $examen) {
            $examen->personalControl()->syncWithoutDetaching([$control->id]);

            $habilitaciones = Habilitacion::query()
                ->where('id_examen', $examen->id_examen)
                ->get();

            foreach ($habilitaciones as $index => $habilitacion) {
                if ($index < 10 && $habilitacion->id_personal_control === null) {
                    $habilitacion->update(['id_personal_control' => $control->id]);
                }

                ExamenQr::paraHabilitacion($habilitacion);
            }
        }

        $examenDemo = $examenes->first();
        if (! $examenDemo) {
            return;
        }

        $ambiente = ExamenAmbiente::where('id_examen', $examenDemo->id_examen)->first();
        $habilitados = Habilitacion::where('id_examen', $examenDemo->id_examen)
            ->where('estado_habilitado', true)
            ->orderBy('id_habilitacion')
            ->get();

        if ($ambiente && $habilitados->isNotEmpty()) {
            $primerEstudiante = $habilitados->first();

            RegistroIngreso::firstOrCreate(
                [
                    'id_estudiante' => $primerEstudiante->id_estudiante,
                    'id_examen_ambiente' => $ambiente->id_examen_ambiente,
                ],
                [
                    'id_usuario' => $control->id,
                    'fecha_hora_ingreso' => now()->subMinutes(3),
                ]
            );

            $segundoEstudiante = $habilitados->get(1);
            if ($segundoEstudiante) {
                Incidencia::firstOrCreate(
                    [
                        'id_examen' => $examenDemo->id_examen,
                        'id_estudiante' => $segundoEstudiante->id_estudiante,
                        'tipo_incidencia' => 'Problema de identificación',
                    ],
                    [
                        'id_usuario' => $control->id,
                        'descripcion_motivo' => 'Registro demo: documento presentado requiere verificación manual.',
                        'fecha_hora' => now()->subMinutes(2),
                    ]
                );
            }
        }

        AuditoriaLog::firstOrCreate(
            [
                'id_usuario' => $admin->id,
                'tabla_afectada' => 'examen',
                'id_registro_afectado' => $examenDemo->id_examen,
                'accion' => 'created',
            ],
            ['fecha_hora' => now()->subHour()]
        );
    }
}
