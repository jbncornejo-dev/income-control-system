<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Rol::create(['nombre_rol' => $roleName]);

        return User::create([
            'id_rol' => $role->id_rol,
            'name' => ucfirst($roleName),
            'username' => str($roleName)->slug('_'),
            'email' => str($roleName)->slug().'@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_personal_de_control_cannot_create_students(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->userWithRole('personal de control de ingreso');

        $response = $this->actingAs($user)->post('/estudiantes', []);

        $response->assertForbidden();
    }

    public function test_docente_cannot_import_students(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->userWithRole('docente');

        $response = $this->actingAs($user)->post('/estudiantes/importar', []);

        $response->assertForbidden();
    }

    public function test_administrador_can_access_student_management_routes(): void
    {
        $user = $this->userWithRole('administrador');

        $response = $this->actingAs($user)->get('/estudiantes');

        $response->assertOk();
    }
}
