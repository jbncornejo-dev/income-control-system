<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    /**
     * Crea las combinaciones de grupos posibles para el docente de desarrollo.
     *
     * La clave única (id_asignatura, nombre_grupo, gestion) permite que un mismo
     * docente tenga varios grupos en una asignatura (A, B, C...), pero impide que
     * dos docentes compartan la misma sección en la misma gestión.
     */
    public function run(): void
    {
        $docente = User::where('email', 'docente@example.com')->first();

        if ($docente === null) {
            return;
        }

        $gestion = (string) now()->year;

        // Asignaturas que dicta el docente con sus grupos: todas las combinaciones
        // permitidas, desde una sola sección hasta tres en la misma asignatura.
        $combinaciones = [
            'Cálculo II' => ['A', 'B'],
            'Física I' => ['A'],
            'Programación I' => ['A', 'B'],
            'Álgebra Lineal' => ['A', 'B', 'C'],
        ];

        foreach ($combinaciones as $nombreAsignatura => $grupos) {
            $asignatura = Asignatura::where('nombre_asignatura', $nombreAsignatura)->first();

            if ($asignatura === null) {
                continue;
            }

            foreach ($grupos as $nombreGrupo) {
                Grupo::firstOrCreate(
                    [
                        'id_asignatura' => $asignatura->id_asignatura,
                        'nombre_grupo' => $nombreGrupo,
                        'gestion' => $gestion,
                    ],
                    ['id_usuario' => $docente->id]
                );
            }
        }
    }
}
