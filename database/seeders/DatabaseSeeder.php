<?php

namespace Database\Seeders;

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
    }
}
