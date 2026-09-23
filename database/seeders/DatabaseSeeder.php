<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            'administrador',
            'docente',
            'personal de control de ingreso',
            'estudiante',
        ])->mapWithKeys(fn (string $nombreRol) => [
            $nombreRol => Rol::firstOrCreate(['nombre_rol' => $nombreRol]),
        ]);

        $usuariosDesarrollo = [
            [
                'name' => 'Administrador de Desarrollo',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'rol' => 'administrador',
            ],
            [
                'name' => 'Docente de Desarrollo',
                'username' => 'docente',
                'email' => 'docente@example.com',
                'rol' => 'docente',
            ],
            [
                'name' => 'Docente de Desarrollo 2',
                'username' => 'docente2',
                'email' => 'docente2@example.com',
                'rol' => 'docente',
            ],
            [
                'name' => 'Personal de Control de Desarrollo',
                'username' => 'control',
                'email' => 'control@example.com',
                'rol' => 'personal de control de ingreso',
            ],
            [
                'name' => 'Estudiante de Desarrollo',
                'username' => 'estudiante',
                'email' => 'estudiante@example.com',
                'rol' => 'estudiante',
            ],
        ];

        foreach ($usuariosDesarrollo as $usuario) {
            User::updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'id_rol' => $roles[$usuario['rol']]->id_rol,
                    'name' => $usuario['name'],
                    'username' => $usuario['username'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('pass'),
                ]
            );
        }

        $this->call([
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
        ]);

        // La cuenta demo "estudiante / pass" se vincula a una matrícula real
        // (primer estudiante del demo), así al entrar ve sus exámenes en
        // /mis-examenes. Requiere que EstudianteSeeder ya haya corrido.
        $estudianteDemo = Estudiante::query()->orderBy('id_estudiante')->first();

        if ($estudianteDemo) {
            User::query()->where('username', 'estudiante')->update([
                'id_estudiante' => $estudianteDemo->id_estudiante,
            ]);
        }
    }
}
