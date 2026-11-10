<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    /**
     * Crea los grupos de los docentes de desarrollo.
     *
     * Cada grupo pertenece a un único docente (clave única asignatura +
     * nombre_grupo + gestion). El segundo docente comparte asignaturas con el
     * primero para que el formulario de exámenes muestre "sección — docente"
     * y se distinga a quién corresponde cada grupo.
     */
    public function run(): void
    {
        $gestion = (string) now()->year;

        $docentes = User::whereIn('email', ['docente@example.com', 'docente2@example.com'])
            ->get()
            ->keyBy('email');

        // Grupos por docente: combinaciones permitidas — desde una sección hasta
        // tres en la misma asignatura — y en asignaturas compartidas los grupos
        // del segundo docente son distintos de los del primero.
        $porDocente = [
            'docente@example.com' => [
                'Cálculo II' => ['A', 'B'],
                'Física I' => ['A'],
                'Programación I' => ['A', 'B'],
                'Álgebra Lineal' => ['A', 'B', 'C'],
                'Estadística' => ['A'],
                'Fisiología' => ['A'],
                'Anatomía Humana' => ['A'],
                'Redacción Académica' => ['A'],
            ],
            'docente2@example.com' => [
                'Cálculo II' => ['C'],
                'Física I' => ['B'],
                'Programación I' => ['C'],
                'Fisiología' => ['B'],
            ],
        ];

        foreach ($porDocente as $email => $asignaturas) {
            $docente = $docentes->get($email);

            if ($docente === null) {
                continue;
            }

            foreach ($asignaturas as $nombreAsignatura => $grupos) {
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
}
