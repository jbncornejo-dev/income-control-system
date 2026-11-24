<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use App\Support\AdminCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProvisionarAdministrador extends Command
{
    protected $signature = 'admin:provisionar {--rotar= : ID de una cuenta administrativa existente}';

    protected $description = 'Crea una cuenta administrativa individual o rota sus credenciales';

    public function handle(): int
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'administrador']);
        $usuario = new User;

        if ($this->option('rotar') !== null) {
            $usuario = $rol->users()->find($this->option('rotar'));
            if (! $usuario || $usuario->id_estudiante !== null) {
                $this->error('No se encontró una cuenta administrativa independiente con ese ID.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make([
            'name' => $this->ask('Nombre completo', $usuario->name),
            'username' => $this->ask('Nombre de usuario individual'),
            'email' => $this->ask('Correo institucional', $usuario->email),
            'password' => $this->secret('Contraseña', false),
            'password_confirmation' => $this->secret('Repite la contraseña', false),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', AdminCredentials::USERNAME, 'not_in:admin,administrador', Rule::unique('users')->ignore($usuario)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($usuario)],
            'password' => ['required', 'string', 'max:72', 'confirmed', AdminCredentials::password()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($usuario, $rol, $validator) {
            $usuario->fill($validator->validated())->forceFill([
                'id_rol' => $rol->id_rol,
                'email_verified_at' => now(),
                'activo' => true,
                'debe_cambiar_password' => false,
                'remember_token' => null,
            ])->save();

            $usuario->revocarSesiones();
        });

        $this->info('Cuenta administrativa lista. ID: '.$usuario->id);

        return self::SUCCESS;
    }
}
