<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EstudianteStoreTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $rol = Rol::create(['nombre_rol' => 'administrador']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Tester',
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_guest_cannot_create_estudiante(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $response = $this->post('/estudiantes', [
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_estudiante(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/estudiantes', [
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => null,
        ]);

        $response->assertRedirect(route('estudiantes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('estudiante', [
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
        ]);
    }

    public function test_rejects_duplicate_codigo_universitario(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user = $this->createUser();
        Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);

        $response = $this->actingAs($user)->post('/estudiantes', [
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '2222222',
            'nombres' => 'Juan',
            'apellidos' => 'Gomez',
        ]);

        $response->assertSessionHasErrors('codigo_universitario');
        $this->assertDatabaseCount('estudiante', 1);
    }

    public function test_rejects_duplicate_documento_identidad(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user = $this->createUser();
        Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);

        $response = $this->actingAs($user)->post('/estudiantes', [
            'codigo_universitario' => '2020-00002',
            'documento_identidad' => '1111111',
            'nombres' => 'Juan',
            'apellidos' => 'Gomez',
        ]);

        $response->assertSessionHasErrors('documento_identidad');
        $this->assertDatabaseCount('estudiante', 1);
    }

    public function test_rejects_missing_required_fields(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/estudiantes', [
            'codigo_universitario' => '',
            'documento_identidad' => '',
            'nombres' => '',
            'apellidos' => '',
        ]);

        $response->assertSessionHasErrors(['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos']);
    }

    public function test_rejects_duplicate_codigo_qr(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user = $this->createUser();
        Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-123',
        ]);

        $response = $this->actingAs($user)->post('/estudiantes', [
            'codigo_universitario' => '2020-00002',
            'documento_identidad' => '2222222',
            'nombres' => 'Juan',
            'apellidos' => 'Gomez',
            'codigo_qr' => 'QR-123',
        ]);

        $response->assertSessionHasErrors('codigo_qr');
    }
}
