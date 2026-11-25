<?php

namespace Database\Seeders;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAuditoriaDetalleSeeder extends Seeder
{
    public function run(): void
    {
        AuditoriaLog::query()
            ->whereNull('datos_nuevos')
            ->orWhereNull('datos_anteriores')
            ->get()
            ->each(function (AuditoriaLog $log): void {
                $anteriores = null;
                $nuevos = null;

                if ($log->tabla_afectada === 'examen') {
                    $modelo = Examen::find($log->id_registro_afectado);
                    $nuevos = $modelo?->getAttributes() ?? [
                        'id_examen' => $log->id_registro_afectado,
                        'fecha' => now()->toDateString(),
                        'duracion_minutos' => 120,
                        'normas_generales' => 'Examen demo',
                    ];
                } elseif ($log->tabla_afectada === 'grupo') {
                    $modelo = Grupo::find($log->id_registro_afectado);
                    $nuevos = $modelo?->getAttributes() ?? [
                        'id_grupo' => $log->id_registro_afectado,
                        'nombre_grupo' => 'A',
                        'gestion' => (string) now()->year,
                    ];
                    $anteriores = $nuevos;
                    $anteriores['nombre_grupo'] = 'B';
                } elseif ($log->tabla_afectada === 'users') {
                    $modelo = User::find($log->id_registro_afectado);
                    $nuevos = $modelo?->getAttributes() ?? [
                        'id' => $log->id_registro_afectado,
                        'name' => 'Usuario demo',
                        'username' => 'demo',
                    ];
                }

                foreach (['password', 'remember_token', 'token'] as $campo) {
                    unset($anteriores[$campo], $nuevos[$campo]);
                }

                $log->update([
                    'datos_anteriores' => $log->accion === 'UPDATE' ? $anteriores : null,
                    'datos_nuevos' => $nuevos,
                ]);
            });
    }
}
