<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Rol::firstOrCreate(['nombre_rol' => $roleName]);

        return User::create([
            'id_rol' => $role->id_rol,
            'name' => ucfirst($roleName),
            'username' => str($roleName)->slug('_'),
            'email' => str($roleName)->slug().'@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function estudianteConCuenta(string $codigo = '201809372', string $documento = '1111111'): array
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'estudiante']);
        $estudiante = Estudiante::create([
            'codigo_universitario' => $codigo,
            'documento_identidad' => $documento,
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'email' => '201809372@est.umss.edu',
        ]);
        $cuenta = User::create([
            'id_rol' => $rol->id_rol,
            'id_estudiante' => $estudiante->id_estudiante,
            'name' => 'Ana Perez',
            'username' => $codigo,
            'email' => '201809372@est.umss.edu',
            'password' => Hash::make($documento),
            'debe_cambiar_password' => true,
        ]);

        return [$estudiante, $cuenta];
    }

    public function test_login_exitoso_redirecciona_al_dashboard()
    {
        // En la implementación actual, todos los roles redirigen a /dashboard.
        // El controlador de tráfico del dashboard decide qué mostrar.

        // Administrador
        $admin = $this->userWithRole('administrador');
        $this->post('/login', [
            'identificador' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->post('/logout');

        // Docente
        $docente = $this->userWithRole('docente');
        $this->post('/login', [
            'identificador' => $docente->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->post('/logout');

        // Personal de Control
        $control = $this->userWithRole('personal de control de ingreso');
        $this->post('/login', [
            'identificador' => $control->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }

    public function test_login_por_username_de_estudiante_exige_cambio_de_password()
    {
        [$estudiante, $cuenta] = $this->estudianteConCuenta();

        $response = $this->post('/login', [
            'identificador' => $estudiante->codigo_universitario,
            'password' => $estudiante->documento_identidad,
        ]);

        $response->assertRedirect(route('cambiar-password.show'));
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_login_por_email_de_estudiante_exige_cambio_de_password()
    {
        [, $cuenta] = $this->estudianteConCuenta();

        $this->post('/login', [
            'identificador' => $cuenta->email,
            'password' => '1111111',
        ])->assertRedirect(route('cambiar-password.show'));
    }

    public function test_login_por_documento_de_identidad()
    {
        [$estudiante, $cuenta] = $this->estudianteConCuenta();

        $response = $this->post('/login', [
            'identificador' => $estudiante->documento_identidad,
            'password' => $estudiante->documento_identidad,
        ]);

        $response->assertRedirect(route('cambiar-password.show'));
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_credenciales_erroneas_son_bloqueadas()
    {
        $user = $this->userWithRole('administrador');

        $response = $this->post('/login', [
            'identificador' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'identificador' => 'Credenciales inválidas.',
        ]);
    }

    public function test_identificador_desconocido_es_bloqueado()
    {
        $this->post('/login', [
            'identificador' => 'noexiste@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_persistencia_de_sesion()
    {
        $user = $this->userWithRole('administrador');

        // Login inicial
        $this->post('/login', [
            'identificador' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        // Acceso a ruta protegida sin volver a enviar credenciales
        $this->get('/ambientes')->assertOk();
    }

    public function test_bloqueo_de_rutas_por_rol_con_respuesta_403()
    {
        $docente = $this->userWithRole('docente');
        $this->actingAs($docente);

        // Docente intentando acceder a rutas de administrador
        $response = $this->get('/ambientes');

        $response->assertForbidden();
    }
}
