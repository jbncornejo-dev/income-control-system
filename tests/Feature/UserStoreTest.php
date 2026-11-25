<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_user_screen_creates_student_account_with_ci_as_initial_password(): void
    {
        $adminRole = Rol::create(['nombre_rol' => 'administrador']);
        $studentRole = Rol::create(['nombre_rol' => 'estudiante']);
        $admin = User::create([
            'id_rol' => $adminRole->id_rol,
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($admin)->post('/usuarios', [
            'id_rol' => $studentRole->id_rol,
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_universitario' => '201809372',
            'documento_identidad' => '1111111',
            'email' => '201809372@est.umss.edu',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('estudiante', [
            'codigo_universitario' => '201809372',
            'documento_identidad' => '1111111',
            'email' => '201809372@est.umss.edu',
        ]);

        $cuenta = User::where('username', '201809372')->firstOrFail();
        $this->assertSame('Ana Perez', $cuenta->name);
        $this->assertSame('201809372@est.umss.edu', $cuenta->email);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue(Hash::check('1111111', $cuenta->password));
        $this->assertNotNull($cuenta->id_estudiante);
    }

    public function test_generic_user_also_uses_ci_as_initial_password(): void
    {
        $adminRole = Rol::create(['nombre_rol' => 'administrador']);
        $teacherRole = Rol::create(['nombre_rol' => 'docente']);
        $admin = User::create([
            'id_rol' => $adminRole->id_rol,
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($admin)->post('/usuarios', [
            'id_rol' => $teacherRole->id_rol,
            'nombres' => 'Docente',
            'apellidos' => 'Uno',
            'codigo_universitario' => '201809373',
            'documento_identidad' => '2222222',
            'email' => 'docente@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $cuenta = User::where('username', '201809373')->firstOrFail();
        $this->assertSame('Docente Uno', $cuenta->name);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue(Hash::check('2222222', $cuenta->password));
    }
}
