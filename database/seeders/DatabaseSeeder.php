<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (array_keys(Rol::ROLES_BASE) as $nombreRol) {
            Rol::firstOrCreate(['nombre_rol' => $nombreRol]);
        }

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            DemoUserSeeder::class,
            AmbienteSeeder::class,
            AsignaturaSeeder::class,
            PeriodoSeeder::class,
            // Los tipos se crean antes que los exámenes: el ExamenSeeder los
            // referencia (id_tipo_examen) al construir cada examen del demo.
            TipoExamenSeeder::class,
            GrupoSeeder::class,
            EstudianteSeeder::class,
            ExamenSeeder::class,
            InscripcionSeeder::class,
            HabilitacionSeeder::class,
            DemoOperativoSeeder::class,
            DemoAuditoriaDetalleSeeder::class,
            DemoIncidenciasSeeder::class,
            DemoHUsSeeder::class,
        ]);

        // Vincula la cuenta demo para que pueda consultar sus exámenes.
        $estudianteDemo = Estudiante::query()->orderBy('id_estudiante')->first();

        if ($estudianteDemo) {
            User::query()->where('username', 'estudiante')->update([
                'id_estudiante' => $estudianteDemo->id_estudiante,
            ]);
        }
    }
}
