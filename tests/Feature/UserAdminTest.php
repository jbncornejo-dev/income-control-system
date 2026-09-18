<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Rol::create(['nombre_rol' => 'administrador']);
        Rol::create(['nombre_rol' => 'docente']);
        Rol::create(['nombre_rol' => 'personal de control de ingreso']);
        Rol::create(['nombre_rol' => 'estudiante']);
        Rol::create(['nombre_rol' => 'otro_rol']); // Invalid role
    }

    public function test_admin_can_access_usuarios()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $response = $this->actingAs($admin)->get('/usuarios');
        $response->assertStatus(200);
    }

    public function test_docente_cannot_access_usuarios()
    {
        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        $docente = User::factory()->create(['id_rol' => $docenteRol->id_rol]);

        $response = $this->actingAs($docente)->get('/usuarios');
        // Because of the role middleware, it should be 403 or redirect
        $response->assertStatus(403);
    }

    public function test_admin_can_create_usuario()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();

        $response = $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'id_rol' => $docenteRol->id_rol,
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_unique_email_validation()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol, 'email' => 'admin@example.com']);

        User::factory()->create([
            'id_rol' => $adminRol->id_rol,
            'email' => 'existing@example.com'
        ]);

        $response = $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'id_rol' => $adminRol->id_rol,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'El nombre de usuario o email ya está registrado']);
    }

    public function test_invalid_role_validation()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $invalidRol = Rol::where('nombre_rol', 'otro_rol')->first();

        $response = $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'id_rol' => $invalidRol->id_rol,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('id_rol');
    }

    public function test_update_ignores_own_email()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $targetUser = User::factory()->create([
            'id_rol' => $adminRol->id_rol,
            'email' => 'target@example.com'
        ]);

        $response = $this->actingAs($admin)->patch("/usuarios/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => 'target@example.com',
            'id_rol' => $adminRol->id_rol,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'name' => 'Updated Name']);
    }
}
