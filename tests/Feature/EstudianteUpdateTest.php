<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EstudianteUpdateTest extends TestCase
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

    private function crearEstudiante(): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-1',
        ]);
    }

    public function test_guest_cannot_update_estudiante(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $estudiante = $this->crearEstudiante();

        $response = $this->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => 'Ana Maria',
            'apellidos' => 'Perez',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_updates_only_editable_fields(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $estudiante = $this->crearEstudiante();

        $response = $this->actingAs($user)->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => 'Ana Maria',
            'apellidos' => 'Perez Lopez',
            'codigo_qr' => 'QR-NUEVO',
            // Se envían igual que el frontend (inputs disabled), pero no deben cambiarse.
            'codigo_universitario' => '9999-99999',
            'documento_identidad' => '9999999',
        ]);

        $response->assertSessionHasNoErrors();
        $estudiante->refresh();

        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $estudiante->id_estudiante,
            'nombres' => 'Ana Maria',
            'apellidos' => 'Perez Lopez',
            // El QR ya no se modifica a través del CRUD.
            'codigo_qr' => 'QR-1',
        ]);
        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $estudiante->id_estudiante,
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
        ]);
    }

    public function test_keeps_own_codigo_qr_without_unique_conflict(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $estudiante = $this->crearEstudiante();

        $response = $this->actingAs($user)->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('estudiante', 1);
    }

    public function test_cannot_change_codigo_qr_via_update(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $estudiante = $this->crearEstudiante();

        $response = $this->actingAs($user)->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => '',
        ]);

        $response->assertSessionHasNoErrors();
        // El QR ya no forma parte de la edición: no se puede modificar ni limpiar.
        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $estudiante->id_estudiante,
            'codigo_qr' => 'QR-1',
        ]);
    }

    public function test_ignores_duplicate_codigo_qr_on_update(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $estudiante = $this->crearEstudiante();
        Estudiante::create([
            'codigo_universitario' => '2020-00002',
            'documento_identidad' => '2222222',
            'nombres' => 'Juan',
            'apellidos' => 'Gomez',
            'codigo_qr' => 'QR-2',
        ]);

        $response = $this->actingAs($user)->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-2',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $estudiante->id_estudiante,
            'codigo_qr' => 'QR-1',
        ]);
    }

    public function test_requires_nombres_and_apellidos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $estudiante = $this->crearEstudiante();

        $response = $this->actingAs($user)->put("/estudiantes/{$estudiante->id_estudiante}", [
            'nombres' => '',
            'apellidos' => '',
        ]);

        $response->assertSessionHasErrors(['nombres', 'apellidos']);
    }
}
