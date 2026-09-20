<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PDOException;
use ReflectionProperty;
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

    public function test_docente_puede_registrar_un_examen_de_una_asignatura_que_dicta(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $datos = $this->datosValidos();

        $rol = Rol::create(['nombre_rol' => 'docente']);
        $docente = User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);
        Grupo::create([
            'id_asignatura' => $datos['id_asignatura'],
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $response = $this->actingAs($docente)->post('/examenes', $datos);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('examen', [
            'id_asignatura' => $datos['id_asignatura'],
        ]);
    }

    public function test_docente_no_puede_registrar_un_examen_de_una_asignatura_que_no_dicta(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $datos = $this->datosValidos();

        $rol = Rol::create(['nombre_rol' => 'docente']);
        $docente = User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);

        $response = $this->actingAs($docente)->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_asignatura');
        $this->assertDatabaseCount('examen', 0);
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

    public function test_registro_acepta_ambientes_en_cualquier_orden(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Física II']);
        $a = Ambiente::create(['nombre_ambiente' => 'Aula 201', 'capacidad' => 30]);
        $b = Ambiente::create(['nombre_ambiente' => 'Aula 202', 'capacidad' => 30]);

        $response = $this->actingAs($this->administrador())->post('/examenes', [
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
            'id_ambientes' => [$b->id_ambiente, $a->id_ambiente], // En desorden a propósito.
        ]);

        $response->assertSessionHas('success');
        // El orden no importa: ambos ambientes quedan asociados al examen.
        $this->assertDatabaseHas('examen_ambiente', ['id_ambiente' => $a->id_ambiente]);
        $this->assertDatabaseHas('examen_ambiente', ['id_ambiente' => $b->id_ambiente]);
    }

    public function test_devuelve_error_amigable_cuando_el_registro_simultaneo_genera_deadlock(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Simula un deadlock (SQLSTATE 40P01) de Postgres en la transacción.
        $gestor = app('db');
        $gestorMock = Mockery::mock($gestor)->makePartial();
        $gestorMock->shouldReceive('transaction')->andThrow($this->excepcionDeadlock());
        $this->instance('db', $gestorMock);

        $datos = $this->datosValidos();

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_ambientes');
        $this->assertDatabaseCount('examen', 0);
    }

    private function excepcionDeadlock(): QueryException
    {
        $excepcion = new QueryException('pgsql', 'select * from ambiente', [], new PDOException('deadlock detected'));

        (new ReflectionProperty(PDOException::class, 'code'))->setValue($excepcion, '40P01');

        return $excepcion;
    }
}
