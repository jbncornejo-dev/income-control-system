<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstudianteDestroyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function crearEstudiante(): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => '201809372',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);
    }

    private function crearExamen(): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
    }

    private function crearAmbiente(): Ambiente
    {
        return Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }

    private function crearExamenAmbiente(Examen $examen, Ambiente $ambiente): ExamenAmbiente
    {
        return ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);
    }

    public function test_administrador_disables_and_reactivates_student(): void
    {
        $estudiante = $this->crearEstudiante();

        $this->actingAs(User::factory()->create())->from('/estudiantes')->delete(route('estudiantes.destroy', $estudiante))
            ->assertRedirect('/estudiantes')
            ->assertSessionHas('success', 'Estudiante desactivado.');

        $this->assertFalse($estudiante->fresh()->activo);
        $this->patch(route('estudiantes.activate', $estudiante))->assertRedirect();
        $this->assertTrue($estudiante->fresh()->activo);
    }

    public function test_disables_student_with_habilitacion_and_preserves_relationship(): void
    {
        $estudiante = $this->crearEstudiante();
        $examen = $this->crearExamen();
        $habilitacion = Habilitacion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => true,
        ]);

        $this->actingAs(User::factory()->create())->from('/estudiantes')
            ->delete(route('estudiantes.destroy', $estudiante))
            ->assertRedirect('/estudiantes')
            ->assertSessionHas('success', 'Estudiante desactivado.');

        $this->assertFalse($estudiante->fresh()->activo);
        $this->assertModelExists($examen);
        $this->assertModelExists($habilitacion);
    }

    public function test_disables_student_with_registro_ingreso_and_preserves_relationship(): void
    {
        $estudiante = $this->crearEstudiante();
        $examenAmbiente = $this->crearExamenAmbiente($this->crearExamen(), $this->crearAmbiente());
        $usuario = User::factory()->create();
        $registro = RegistroIngreso::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            'id_usuario' => $usuario->id,
            'fecha_hora_ingreso' => now(),
        ]);

        $this->actingAs(User::factory()->create())->from('/estudiantes')
            ->delete(route('estudiantes.destroy', $estudiante))
            ->assertRedirect('/estudiantes')
            ->assertSessionHas('success', 'Estudiante desactivado.');

        $this->assertFalse($estudiante->fresh()->activo);
        $this->assertModelExists($registro);
    }

    public function test_disables_student_with_incidencia_and_preserves_relationship(): void
    {
        $estudiante = $this->crearEstudiante();
        $examen = $this->crearExamen();
        $usuario = User::factory()->create();
        $incidencia = Incidencia::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'id_usuario' => $usuario->id,
            'tipo_incidencia' => 'no_habilitado',
            'descripcion_motivo' => 'Intento de ingreso no autorizado.',
        ]);

        $this->actingAs(User::factory()->create())->from('/estudiantes')
            ->delete(route('estudiantes.destroy', $estudiante))
            ->assertRedirect('/estudiantes')
            ->assertSessionHas('success', 'Estudiante desactivado.');

        $this->assertFalse($estudiante->fresh()->activo);
        $this->assertModelExists($incidencia);
    }

    public function test_disabled_student_cannot_use_an_existing_session_or_log_in(): void
    {
        $estudiante = $this->crearEstudiante();
        $rol = Rol::create(['nombre_rol' => 'estudiante']);
        $usuario = User::factory()->create([
            'id_rol' => $rol->id_rol,
            'id_estudiante' => $estudiante->id_estudiante,
            'password' => 'ClaveEstudiante123',
        ]);
        $administrador = User::factory()->create();

        $this->actingAs($administrador)->delete(route('estudiantes.destroy', $estudiante))->assertRedirect();
        $this->actingAs($usuario)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', [
            'identificador' => $usuario->username, 'password' => 'ClaveEstudiante123',
        ])->assertSessionHasErrors('identificador');
    }

    public function test_nonexistent_student_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->delete(route('estudiantes.destroy', 99999))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $estudiante = $this->crearEstudiante();
        $this->delete(route('estudiantes.destroy', $estudiante))->assertRedirect(route('login'));
        $this->assertModelExists($estudiante);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $estudiante = $this->crearEstudiante();
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->delete(route('estudiantes.destroy', $estudiante))->assertForbidden();
        $this->assertModelExists($estudiante);
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }
}
