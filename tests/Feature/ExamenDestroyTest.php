<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExamenDestroyTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_BLOQUEO = 'No se puede eliminar el examen porque tiene inscripciones de estudiantes o registros de ingreso asociados';

    private static int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function crearAsignatura(): Asignatura
    {
        return Asignatura::create(['nombre_asignatura' => 'Asignatura '.++static::$contador]);
    }

    private function crearExamen(): Examen
    {
        return Examen::create([
            'id_asignatura' => $this->crearAsignatura()->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
    }

    private function crearAmbiente(): Ambiente
    {
        return Ambiente::create([
            'nombre_ambiente' => 'Aula '.++static::$contador,
            'capacidad' => 40,
        ]);
    }

    private function crearExamenAmbiente(Examen $examen, Ambiente $ambiente): ExamenAmbiente
    {
        return ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);
    }

    private function crearEstudiante(): Estudiante
    {
        $n = ++static::$contador;

        return Estudiante::create([
            'codigo_universitario' => '2026-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'documento_identidad' => (string) (20000000 + $n),
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
        ]);
    }

    public function test_administrador_elimina_examen_sin_dependencias_y_sus_ambientes_asociados(): void
    {
        $examen = $this->crearExamen();
        $ambiente = $this->crearAmbiente();
        $otroAmbiente = $this->crearAmbiente();
        $relacion = $this->crearExamenAmbiente($examen, $ambiente);
        $otraRelacion = $this->crearExamenAmbiente($examen, $otroAmbiente);

        $this->actingAs(User::factory()->create())->delete(route('examenes.destroy', $examen))
            ->assertRedirect(route('examenes.index'))
            ->assertSessionHas('success', 'Examen eliminado correctamente.');

        $this->assertModelMissing($examen);
        // Se eliminan las asociaciones con sus ambientes (examen_ambiente).
        $this->assertModelMissing($relacion);
        $this->assertModelMissing($otraRelacion);
        // Los ambientes en sí se conservan porque son compartidos entre exámenes.
        $this->assertModelExists($ambiente);
        $this->assertModelExists($otroAmbiente);
    }

    public function test_no_elimina_examen_con_habilitaciones_y_conserva_relaciones(): void
    {
        $examen = $this->crearExamen();
        $ambiente = $this->crearAmbiente();
        $relacion = $this->crearExamenAmbiente($examen, $ambiente);
        $estudiante = $this->crearEstudiante();
        $habilitacion = Habilitacion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => true,
        ]);

        $this->actingAs(User::factory()->create())->from('/examenes')
            ->delete(route('examenes.destroy', $examen))
            ->assertRedirect('/examenes')
            ->assertSessionHas('error', self::MENSAJE_BLOQUEO)
            ->assertSessionMissing('success');

        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
        $this->assertModelExists($habilitacion);
    }

    public function test_no_elimina_examen_con_registros_de_ingreso_y_conserva_relaciones(): void
    {
        $examen = $this->crearExamen();
        $ambiente = $this->crearAmbiente();
        $relacion = $this->crearExamenAmbiente($examen, $ambiente);
        $estudiante = $this->crearEstudiante();
        $usuario = User::factory()->create();
        $registro = RegistroIngreso::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen_ambiente' => $relacion->id_examen_ambiente,
            'id_usuario' => $usuario->id,
            'fecha_hora_ingreso' => now(),
        ]);

        $this->actingAs(User::factory()->create())->from('/examenes')
            ->delete(route('examenes.destroy', $examen))
            ->assertRedirect('/examenes')
            ->assertSessionHas('error', self::MENSAJE_BLOQUEO)
            ->assertSessionMissing('success');

        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
        $this->assertModelExists($registro);
    }

    public function test_clave_foranea_bloquea_ingreso_creado_entre_verificacion_y_borrado(): void
    {
        $examen = $this->crearExamen();
        $ambiente = $this->crearAmbiente();
        $relacion = $this->crearExamenAmbiente($examen, $ambiente);
        $estudiante = $this->crearEstudiante();
        $usuario = User::factory()->create();
        $this->actingAs(User::factory()->create());
        $registro = null;

        // Un registro de ingreso creado tras la verificación reproduce la ventana
        // de carrera en PostgreSQL: la clave foránea bloquea el borrado en cascada.
        DB::listen(function (QueryExecuted $query) use ($relacion, $estudiante, $usuario, &$registro) {
            if ($registro === null && str_starts_with($query->sql, 'select exists')
                && str_contains($query->sql, '"registro_ingreso"')) {
                $registro = RegistroIngreso::create([
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_examen_ambiente' => $relacion->id_examen_ambiente,
                    'id_usuario' => $usuario->id,
                    'fecha_hora_ingreso' => now(),
                ]);
            }
        });

        $this->from('/examenes')->delete(route('examenes.destroy', $examen))
            ->assertRedirect('/examenes')
            ->assertSessionHas('error', self::MENSAJE_BLOQUEO)
            ->assertSessionMissing('success');

        $this->assertNotNull($registro);
        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
        $this->assertModelExists($registro);
    }

    public function test_examen_inexistente_devuelve_404(): void
    {
        $this->actingAs(User::factory()->create())->delete(route('examenes.destroy', 99999))->assertNotFound();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $examen = $this->crearExamen();
        $this->delete(route('examenes.destroy', $examen))->assertRedirect(route('login'));
        $this->assertModelExists($examen);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_otros_roles_reciben_403(string $roleName): void
    {
        $examen = $this->crearExamen();
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->delete(route('examenes.destroy', $examen))->assertForbidden();
        $this->assertModelExists($examen);
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }
}
