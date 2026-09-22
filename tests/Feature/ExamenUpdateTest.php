<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PDOException;
use ReflectionProperty;
use Tests\TestCase;

class ExamenUpdateTest extends TestCase
{
    use RefreshDatabase;

    private static int $siguienteAmbiente = 1;

    private static function siguienteNombreAmbiente(): string
    {
        return 'Aula '.static::$siguienteAmbiente++;
    }

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

    private function docente(): User
    {
        $rol = Rol::create(['nombre_rol' => 'docente']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sobreescribir
     * @return array{examen: Examen, ambiente: Ambiente, id_ambiente: int}
     */
    private function crearExamenConAmbiente(array $sobreescribir = []): array
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación '.static::$siguienteAmbiente]);
        $ambiente = Ambiente::create([
            'nombre_ambiente' => static::siguienteNombreAmbiente(),
            'capacidad' => 40,
        ]);

        $datos = array_merge([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDays(10)->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
            'normas_generales' => 'Normas originales',
            'id_ambientes' => [$ambiente->id_ambiente],
        ], $sobreescribir);

        $examen = Examen::create([
            'id_asignatura' => $datos['id_asignatura'],
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => $datos['fecha'],
            'hora_inicio' => $datos['hora_inicio'],
            'duracion_minutos' => $datos['duracion_minutos'],
            'normas_generales' => $datos['normas_generales'],
        ]);

        foreach ($datos['id_ambientes'] as $idAmbiente) {
            ExamenAmbiente::create([
                'id_examen' => $examen->id_examen,
                'id_ambiente' => $idAmbiente,
            ]);
        }

        return [
            'examen' => $examen,
            'ambiente' => $ambiente,
            'id_ambiente' => $ambiente->id_ambiente,
        ];
    }

    public function test_administrador_puede_editar_normas_generales_sin_tocar_otros_campos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $response = $this->actingAs($this->administrador())->patch(
            "/examenes/{$examen->id_examen}",
            ['normas_generales' => 'Nuevas normas']
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'normas_generales' => 'Nuevas normas',
            // Los campos no enviados se conservan.
            'duracion_minutos' => 60,
            'hora_inicio' => '10:00',
        ]);
    }

    public function test_administrador_puede_cambiar_duracion_sin_auto_conflicto(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $response = $this->actingAs($this->administrador())->patch(
            "/examenes/{$examen->id_examen}",
            ['duracion_minutos' => 120]
        );

        // Editar la duración del propio examen no debe generar conflicto consigo mismo.
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'duracion_minutos' => 120,
        ]);
    }

    public function test_rechaza_cambio_de_ambiente_que_conflicta_con_otro_examen(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];
        $otro = $this->crearExamenConAmbiente([
            'hora_inicio' => '10:30',
            'duracion_minutos' => 60,
        ]);

        // Mover el examen al ambiente del otro examen: se solapan porque ocurren la misma fecha.
        $response = $this->actingAs($this->administrador())->patch(
            "/examenes/{$examen->id_examen}",
            ['id_ambientes' => [$otro['id_ambiente']]]
        );

        $response->assertSessionHasErrors('id_ambientes');
        // El pivot original se conserva.
        $this->assertDatabaseHas('examen_ambiente', [
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $creado['id_ambiente'],
        ]);
    }

    public function test_bloquea_cambio_de_ambientes_si_el_examen_tiene_ingresos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];
        $pivot = ExamenAmbiente::where('id_examen', $examen->id_examen)->firstOrFail();

        $estudiante = Estudiante::create([
            'codigo_universitario' => '2024-0001',
            'documento_identidad' => '12345678',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'codigo_qr' => 'qr-2024-0001',
        ]);

        $admin = $this->administrador();

        RegistroIngreso::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen_ambiente' => $pivot->id_examen_ambiente,
            'id_usuario' => $admin->id,
            'fecha_hora_ingreso' => now(),
        ]);

        $otroAmbiente = Ambiente::create([
            'nombre_ambiente' => static::siguienteNombreAmbiente(),
            'capacidad' => 45,
        ]);

        $response = $this->actingAs($admin)->patch(
            "/examenes/{$examen->id_examen}",
            ['id_ambientes' => [$otroAmbiente->id_ambiente]]
        );

        $response->assertSessionHasErrors('id_ambientes');
        $this->assertDatabaseHas('examen_ambiente', [
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $creado['id_ambiente'],
        ]);
    }

    public function test_docente_puede_actualizar_un_examen_que_cubre_alguna_de_sus_grupos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $docente = $this->docente();
        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $response = $this->actingAs($docente)->patch(
            "/examenes/{$examen->id_examen}",
            ['normas_generales' => 'Normas editadas por el docente']
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'normas_generales' => 'Normas editadas por el docente',
        ]);
    }

    public function test_docente_no_puede_actualizar_un_examen_que_no_cubre_ninguna_de_sus_grupos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $response = $this->actingAs($this->docente())->patch(
            "/examenes/{$examen->id_examen}",
            ['normas_generales' => 'Intento de docente']
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'normas_generales' => 'Normas originales',
        ]);
    }

    public function test_docente_no_puede_cambiar_a_una_asignatura_que_no_dicta(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $docente = $this->docente();
        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $otraAsignatura = Asignatura::create(['nombre_asignatura' => static::siguienteNombreAmbiente().' externa']);

        $response = $this->actingAs($docente)->patch(
            "/examenes/{$examen->id_examen}",
            ['id_asignatura' => $otraAsignatura->id_asignatura]
        );

        $response->assertSessionHasErrors('id_asignatura');
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'id_asignatura' => $examen->id_asignatura,
        ]);
    }

    public function test_rechaza_horario_en_pasado_al_editar(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $response = $this->actingAs($this->administrador())->patch(
            "/examenes/{$examen->id_examen}",
            [
                'fecha' => now()->subDays(3)->format('Y-m-d'),
                'hora_inicio' => '10:00',
            ]
        );

        $response->assertSessionHasErrors('hora_inicio');
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'fecha' => now()->addDays(10)->format('Y-m-d'),
        ]);
    }

    public function test_devuelve_error_amigable_cuando_la_edicion_simultanea_genera_deadlock(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Simula un deadlock (SQLSTATE 40P01) de Postgres en la transacción.
        $gestor = app('db');
        $gestorMock = Mockery::mock($gestor)->makePartial();
        $gestorMock->shouldReceive('transaction')->andThrow($this->excepcionDeadlock());
        $this->instance('db', $gestorMock);

        $creado = $this->crearExamenConAmbiente();

        $response = $this->actingAs($this->administrador())->patch(
            "/examenes/{$creado['examen']->id_examen}",
            ['normas_generales' => 'Intento con deadlock']
        );

        $response->assertSessionHasErrors('id_ambientes');
        $this->assertDatabaseHas('examen', [
            'id_examen' => $creado['examen']->id_examen,
            'normas_generales' => 'Normas originales',
        ]);
    }

    private function excepcionDeadlock(): QueryException
    {
        $excepcion = new QueryException('pgsql', 'select * from ambiente', [], new PDOException('deadlock detected'));

        (new ReflectionProperty(PDOException::class, 'code'))->setValue($excepcion, '40P01');

        return $excepcion;
    }
}
