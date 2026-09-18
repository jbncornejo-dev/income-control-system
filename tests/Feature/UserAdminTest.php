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

        // Deshabilitar la verificación de existencia de componentes Vue
        // para que las pruebas de backend no fallen si el frontend aún no los crea.
        config(['inertia.testing.ensure_pages_exist' => false]);

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
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'id_rol' => $docenteRol->id_rol]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $user->password));
        $this->assertNotNull($user->username);
        $this->assertStringContainsString('test', $user->username);
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

    public function test_update_modifies_only_role_and_password()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();

        $targetUser = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'username' => 'original_username',
            'id_rol' => $docenteRol->id_rol,
            'password' => \Illuminate\Support\Facades\Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($admin)->put("/usuarios/{$targetUser->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'username' => 'hacked_username',
            'id_rol' => $adminRol->id_rol,
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        // Verifica que los campos protegidos NO cambiaron
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id, 
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'username' => 'original_username',
            'id_rol' => $adminRol->id_rol, // El rol SÍ cambió
        ]);

        // Verifica que la contraseña SÍ cambió
        $updatedUser = User::find($targetUser->id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $updatedUser->password));
    }

    public function test_update_password_route()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $targetUser = User::factory()->create([
            'id_rol' => $adminRol->id_rol,
            'password' => \Illuminate\Support\Facades\Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($admin)->patch("/usuarios/{$targetUser->id}/password", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Contraseña actualizada exitosamente.');
        
        $updatedUser = User::find($targetUser->id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $updatedUser->password));
    }

    public function test_index_lists_users_with_roles()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        User::factory()->create(['id_rol' => $docenteRol->id_rol, 'email' => 'docente1@example.com']);

        $response = $this->actingAs($admin)->get('/usuarios');

        $response->assertStatus(200);
        // Inertia assert to check if usuarios is passed and contains the loaded role
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Usuarios/Index')
            ->has('usuarios.data')
            ->has('roles')
        );
    }

    public function test_index_filters_by_role()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        User::factory()->create(['id_rol' => $docenteRol->id_rol, 'email' => 'docente_filter@example.com']);
        $estudianteRol = Rol::where('nombre_rol', 'estudiante')->first();
        User::factory()->create(['id_rol' => $estudianteRol->id_rol, 'email' => 'estudiante_filter@example.com']);

        // Test with id_rol filter
        $response = $this->actingAs($admin)->get('/usuarios?id_rol=' . $docenteRol->id_rol);
        
        $response->assertStatus(200);
        $response->assertSee('docente_filter@example.com');
        $response->assertDontSee('estudiante_filter@example.com');
    }

    public function test_index_filters_by_email_search()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        User::factory()->create(['id_rol' => $adminRol->id_rol, 'email' => 'findme@example.com']);
        User::factory()->create(['id_rol' => $adminRol->id_rol, 'email' => 'hidden@example.com']);

        $response = $this->actingAs($admin)->get('/usuarios?search=findme');

        $response->assertStatus(200);
        $response->assertSee('findme@example.com');
        $response->assertDontSee('hidden@example.com');
    }

    public function test_index_combines_filters()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        User::factory()->create(['id_rol' => $docenteRol->id_rol, 'email' => 'unique_docente@example.com']);
        User::factory()->create(['id_rol' => $adminRol->id_rol, 'email' => 'unique_admin@example.com']);

        $response = $this->actingAs($admin)->get('/usuarios?id_rol=' . $docenteRol->id_rol . '&search=unique');

        $response->assertStatus(200);
        $response->assertSee('unique_docente@example.com');
        $response->assertDontSee('unique_admin@example.com'); // Mismo query text pero distinto rol
    }

    public function test_admin_can_delete_other_user()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        $targetUser = User::factory()->create(['id_rol' => $docenteRol->id_rol]);

        $response = $this->actingAs($admin)->delete("/usuarios/{$targetUser->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Usuario eliminado exitosamente.');
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_admin_cannot_delete_self()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $response = $this->actingAs($admin)->delete("/usuarios/{$admin->id}");

        // Un ValidationException lanzado produce un redirect de vuelta con errores en la sesión
        $response->assertSessionHasErrors(['error' => 'No puedes eliminar tu propia cuenta.']);
        // Verify user still exists
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_not_found_on_delete()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $response = $this->actingAs($admin)->delete("/usuarios/99999");

        $response->assertStatus(404);
    }

    public function test_docente_cannot_modify_or_delete_usuarios()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $targetUser = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first();
        $docente = User::factory()->create(['id_rol' => $docenteRol->id_rol]);

        // Intentar hacer PUT
        $responsePatch = $this->actingAs($docente)->put("/usuarios/{$targetUser->id}", [
            'id_rol' => $docenteRol->id_rol,
            'password' => 'hackedpassword',
        ]);
        $responsePatch->assertStatus(403);

        // Intentar hacer DELETE
        $responseDelete = $this->actingAs($docente)->delete("/usuarios/{$targetUser->id}");
        $responseDelete->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_usuarios()
    {
        $adminRol = Rol::where('nombre_rol', 'administrador')->first();
        $targetUser = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        // Attempting without actingAs
        $responseGet = $this->get('/usuarios');
        $responseGet->assertRedirect('/login');

        $responsePatch = $this->put("/usuarios/{$targetUser->id}", [
            'id_rol' => $adminRol->id_rol,
            'password' => 'hackedpassword',
        ]);
        $responsePatch->assertRedirect('/login');
    }
}
