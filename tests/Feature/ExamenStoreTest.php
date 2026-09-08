<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamenStoreTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $rol = Rol::create(['nombre_rol' => 'administrador']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    private function datosValidos(): array
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación I']);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);

        return [
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
            'normas_generales' => 'Presentar documento de identidad.',
            'id_ambientes' => [$ambiente->id_ambiente],
        ];
    }

    public function test_administrador_can_register_an_exam_with_its_rooms(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $datos = $this->datosValidos();

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('examen', [
            'id_asignatura' => $datos['id_asignatura'],
            'fecha' => $datos['fecha'],
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);
        $this->assertDatabaseHas('examen_ambiente', [
            'id_ambiente' => $datos['id_ambientes'][0],
        ]);
    }

    public function test_non_administrator_cannot_register_an_exam(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $rol = Rol::create(['nombre_rol' => 'docente']);
        $docente = User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);

        $response = $this->actingAs($docente)->post('/examenes', $this->datosValidos());

        $response->assertForbidden();
    }

    public function test_rejects_invalid_or_missing_exam_data(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($this->administrador())->post('/examenes', [
            'id_asignatura' => 999,
            'fecha' => '10/10/2026',
            'hora_inicio' => '10:00:30',
            'duracion_minutos' => 0,
            'id_ambientes' => [],
        ]);

        $response->assertSessionHasErrors([
            'id_asignatura',
            'fecha',
            'hora_inicio',
            'duracion_minutos',
            'id_ambientes',
        ]);
    }

    public function test_rejects_an_exam_that_overlaps_a_room_booking(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $datos = $this->datosValidos();
        $examen = Examen::create([
            'id_asignatura' => $datos['id_asignatura'],
            'fecha' => $datos['fecha'],
            'hora_inicio' => '09:30',
            'duracion_minutos' => 90,
        ]);
        ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $datos['id_ambientes'][0],
        ]);

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_ambientes');
        $this->assertDatabaseCount('examen', 1);
    }

    public function test_rejects_a_start_datetime_in_the_past(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $datos = $this->datosValidos();
        $datos['fecha'] = Carbon::now()->subDay()->format('Y-m-d');

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('fecha');
    }
}
