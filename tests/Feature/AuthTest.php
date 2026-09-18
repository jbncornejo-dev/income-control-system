<?php

namespace Tests\Feature;

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

    public function test_login_exitoso_redirecciona_al_dashboard()
    {
        // En la implementación actual, todos los roles redirigen a /dashboard.
        // El controlador de tráfico del dashboard decide qué mostrar.
        
        // Administrador
        $admin = $this->userWithRole('administrador');
        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->post('/logout');

        // Docente
        $docente = $this->userWithRole('docente');
        $this->post('/login', [
            'email' => $docente->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->post('/logout');

        // Personal de Control
        $control = $this->userWithRole('personal de control de ingreso');
        $this->post('/login', [
            'email' => $control->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }

    public function test_credenciales_erroneas_son_bloqueadas()
    {
        $user = $this->userWithRole('administrador');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'Credenciales inválidas.', 
        ]);
    }

    public function test_persistencia_de_sesion()
    {
        $user = $this->userWithRole('administrador');

        // Login inicial
        $this->post('/login', [
            'email' => $user->email,
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
