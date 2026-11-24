<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

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
                ['username' => $usuario['username']],
                [
                    'id_rol' => Rol::where('nombre_rol', $usuario['rol'])->firstOrFail()->id_rol,
                    'name' => $usuario['name'],
                    'username' => $usuario['username'],
                    'email' => $usuario['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('pass'),
                    'debe_cambiar_password' => false,
                ]
            );
        }
    }
}
