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
                'username' => 'administrador.local',
                'email' => 'administrador@gmail.com',
                'password' => 'SalchiPapa123*',
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
            $idRol = Rol::where('nombre_rol', $usuario['rol'])->firstOrFail()->id_rol;
            $identificador = ['username' => $usuario['username']];

            if ($usuario['rol'] === 'administrador') {
                $anterior = User::where('username', 'admin')->where('email', 'admin@example.com')
                    ->where('id_rol', $idRol)->first();
                if ($anterior && ! User::where('username', $usuario['username'])->exists()) {
                    $identificador = ['id' => $anterior->id];
                }
            }

            User::updateOrCreate(
                $identificador,
                [
                    'id_rol' => $idRol,
                    'name' => $usuario['name'],
                    'username' => $usuario['username'],
                    'email' => $usuario['email'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($usuario['password'] ?? 'pass'),
                    'debe_cambiar_password' => false,
                ]
            );
        }
    }
}
