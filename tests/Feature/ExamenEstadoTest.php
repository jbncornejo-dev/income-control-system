<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamenEstadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        Carbon::setTestNow('2026-09-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $nombreRol, string $sufijo = ''): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($nombreRol).$sufijo,
            'username' => str($nombreRol)->slug('_').$sufijo,
            'email' => str($nombreRol)->slug().$sufijo.'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearExamen(array $atributos = []): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Estados '.uniqid()]);

        return Examen::create(array_merge([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-21',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ], $atributos));
    }

    public function test_estado_actual_se_deriva_automaticamente_del_horario(): void
    {
        // Ahora es 2026-09-20 10:00 (America/La_Paz).

        $programado = $this->crearExamen(['fecha' => '2026-09-21', 'hora_inicio' => '10:00', 'duracion_minutos' => 90]);
        $this->assertSame('programado', $programado->estado_actual);

        // En curso: 09:30 + 120 min -> termina a las 11:30, ahora es 10:00.
        $enCurso = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $this->assertSame('en_curso', $enCurso->estado_actual);

        // Finalizado: 08:00 + 90 min -> terminó a las 09:30.
        $finalizado = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]);
        $this->assertSame('finalizado', $finalizado->estado_actual);
    }

    public function test_estado_cancelado_y_anulado_son_estados_de_gestion_con_prioridad(): void
    {
        // 'cancelado' (programado que se llama off) y 'anulado' (en curso que se
        // invalida) son estados terminales propios: no se derivan del horario.
        $cancelado = $this->crearExamen(['estado' => 'cancelado']);
        $anulado = $this->crearExamen(['fecha' => '2026-09-22', 'estado' => 'anulado']);

        $this->assertSame('cancelado', $cancelado->estado_actual);
        $this->assertDatabaseHas('examen', ['id_examen' => $cancelado->id_examen, 'estado' => 'cancelado']);

        $this->assertSame('anulado', $anulado->estado_actual);
        $this->assertDatabaseHas('examen', ['id_examen' => $anulado->id_examen, 'estado' => 'anulado']);
    }

    public function test_estado_suspendido_es_un_estado_propio_y_no_se_mezcla_con_el_ciclo(): void
    {
        // Suspendido es un estado de gestión propio: el listado lo muestra como
        // "Suspendido" (no como "En curso" ni "Programado"). El ciclo de vida
        // sigue corriendo bajo la superficie en estado_horario: la duración y
        // la ventana no se alteran.

        // En curso (09:30 + 120 min -> 11:30; ahora 10:00) pese a estar suspendido.
        $enCurso = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120, 'estado' => 'suspendido']);
        $this->assertSame('suspendido', $enCurso->estado_actual);
        $this->assertSame('en_curso', $enCurso->estado_horario);

        // Programado pese a estar suspendido (solo posible si se marcó antes de la ventana).
        $programado = $this->crearExamen(['fecha' => '2026-09-21', 'estado' => 'suspendido']);
        $this->assertSame('suspendido', $programado->estado_actual);
        $this->assertSame('programado', $programado->estado_horario);
    }

    public function test_no_se_puede_reanudar_un_examen_cancelado_o_anulado(): void
    {
        $admin = $this->usuario('administrador');

        foreach (['cancelado', 'anulado'] as $terminal) {
            $examen = $this->crearExamen(['estado' => $terminal]);

            $respuesta = $this->actingAs($admin)
                ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'reanudar']);

            $respuesta->assertSessionHasErrors('estado');
            $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => $terminal]);
            $this->assertDatabaseCount('auditoria_log', 0);
        }
    }

    public function test_no_se_puede_suspender_un_examen_cancelado_o_anulado(): void
    {
        $admin = $this->usuario('administrador');

        foreach (['cancelado', 'anulado'] as $terminal) {
            $examen = $this->crearExamen(['estado' => $terminal]);

            $respuesta = $this->actingAs($admin)
                ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'suspender']);

            $respuesta->assertSessionHasErrors('estado');
            $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => $terminal]);
            $this->assertDatabaseCount('auditoria_log', 0);
        }
    }

    public function test_no_se_puede_suspender_un_examen_programado(): void
    {
        $examen = $this->crearExamen(); // programado (2026-09-21)

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'suspender']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_editar_un_examen_cancelado_o_anulado(): void
    {
        $admin = $this->usuario('administrador');

        foreach (['cancelado', 'anulado'] as $terminal) {
            $examen = $this->crearExamen(['estado' => $terminal]);

            // La página de edición queda bloqueada.
            $this->actingAs($admin)->get("/examenes/{$examen->id_examen}/editar")->assertForbidden();

            // La edición (PATCH) también es rechazada y no modifica nada.
            $respuesta = $this->actingAs($admin)
                ->patch("/examenes/{$examen->id_examen}", ['duracion_minutos' => 200]);

            $respuesta->assertSessionHasErrors('estado');
            $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'duracion_minutos' => 90]);
        }
    }

    public function test_no_se_puede_editar_un_examen_finalizado(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-19', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]);
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->get("/examenes/{$examen->id_examen}/editar")->assertForbidden();

        $respuesta = $this->actingAs($admin)
            ->patch("/examenes/{$examen->id_examen}", ['duracion_minutos' => 200]);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'duracion_minutos' => 90]);
    }

    public function test_se_pueden_editar_las_normas_de_un_examen_en_curso(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $admin = $this->usuario('administrador');

        // La página de edición sigue disponible para actualizar normas.
        $this->actingAs($admin)->get("/examenes/{$examen->id_examen}/editar")->assertOk();

        $respuesta = $this->actingAs($admin)
            ->patch("/examenes/{$examen->id_examen}", ['normas_generales' => 'Nuevas normas en curso']);

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseHas('examen', [
            'id_examen' => $examen->id_examen,
            'normas_generales' => 'Nuevas normas en curso',
            'duracion_minutos' => 120,
        ]);
    }

    public function test_no_se_pueden_editar_campos_estructurales_de_un_examen_en_curso(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $admin = $this->usuario('administrador');

        // Duración, ambientes, asignatura, fecha y hora quedan congelados en curso.
        $respuesta = $this->actingAs($admin)
            ->patch("/examenes/{$examen->id_examen}", ['duracion_minutos' => 200]);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'duracion_minutos' => 120]);
    }

    public function test_administrador_anula_un_examen_en_curso_y_se_registra_en_auditoria(): void
    {
        // Anular corresponde a un examen en curso (09:30 + 120 min; ahora 10:00).
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => 'anulado']);
        $this->assertDatabaseHas('auditoria_log', [
            'tabla_afectada' => 'examen',
            'id_registro_afectado' => $examen->id_examen,
            'accion' => 'ANULAR',
        ]);
        $this->assertSame(1, AuditoriaLog::count());
    }

    public function test_administrador_cancela_un_examen_programado_y_se_registra_en_auditoria(): void
    {
        // Cancelar corresponde a un examen programado (aún no ha comenzado).
        $examen = $this->crearExamen(['fecha' => '2026-09-21']);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'cancelar']);

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => 'cancelado']);
        $this->assertDatabaseHas('auditoria_log', [
            'tabla_afectada' => 'examen',
            'id_registro_afectado' => $examen->id_examen,
            'accion' => 'CANCELAR',
        ]);
        $this->assertSame(1, AuditoriaLog::count());
    }

    public function test_administrador_suspende_y_reanuda_un_examen(): void
    {
        // Suspender solo aplica sobre un examen en curso (09:30 + 120 min; ahora 10:00).
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'suspender']);
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => 'suspendido']);
        $this->assertDatabaseHas('auditoria_log', ['accion' => 'SUSPENDER']);

        $respuesta = $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'reanudar']);

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseHas('auditoria_log', ['accion' => 'REANUDAR']);
    }

    public function test_no_se_puede_anular_un_examen_programado(): void
    {
        $examen = $this->crearExamen(); // programado

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_cancelar_un_examen_en_curso(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'cancelar']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_cancelar_un_examen_suspendido(): void
    {
        // Suspendido con ventana activa sigue estando en curso: se anula, no se cancela.
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120, 'estado' => 'suspendido']);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'cancelar']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => 'suspendido']);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_anular_un_examen_ya_finalizado(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-19', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_cancelar_un_examen_ya_finalizado(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-19', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'cancelar']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => null]);
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_reanudar_un_examen_sin_estado_manual(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-21']);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'reanudar']);

        $respuesta->assertSessionHasErrors('estado');
    }

    public function test_no_se_puede_anular_dos_veces_el_mismo_examen(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120, 'estado' => 'anulado']);
        $admin = $this->usuario('administrador');

        $respuesta = $this->actingAs($admin)
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_no_se_puede_cancelar_dos_veces_el_mismo_examen(): void
    {
        $examen = $this->crearExamen(['estado' => 'cancelado']);
        $admin = $this->usuario('administrador');

        $respuesta = $this->actingAs($admin)
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'cancelar']);

        $respuesta->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('auditoria_log', 0);
    }

    public function test_docente_puede_cambiar_estado_de_un_examen_que_cubre_alguna_de_sus_grupos(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $docente = $this->usuario('docente');

        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'B',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $respuesta = $this->actingAs($docente)
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'suspender']);

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseHas('examen', ['id_examen' => $examen->id_examen, 'estado' => 'suspendido']);
    }

    public function test_docente_no_puede_cambiar_estado_de_un_examen_que_no_cubre_ninguna_de_sus_grupos(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-21']);

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertForbidden();
    }

    public function test_docente_no_puede_cambiar_estado_de_un_examen_compartido_con_otro_docente(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-21']);
        $docente = $this->usuario('docente');
        $otroDocente = $this->usuario('docente', '_2');

        $propio = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $ajeno = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $otroDocente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'C',
        ]);
        $examen->grupos()->attach([$propio->id_grupo, $ajeno->id_grupo]);

        // Aunque cubre un grupo del docente, el examen no le pertenece por
        // completo: solo el administrador cambia su estado.
        $respuesta = $this->actingAs($docente)
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'suspender']);

        $respuesta->assertForbidden();
    }

    public function test_personal_de_control_no_puede_cambiar_el_estado(): void
    {
        $examen = $this->crearExamen(['fecha' => '2026-09-21']);

        $respuesta = $this->actingAs($this->usuario('personal de control de ingreso'))
            ->patch("/examenes/{$examen->id_examen}/estado", ['accion' => 'anular']);

        $respuesta->assertForbidden();
    }

    public function test_el_listado_incluye_el_estado_actual(): void
    {
        // En tests, `index()` responde JSON (runningUnitTests), no una página Inertia.
        $programado = $this->crearExamen(['fecha' => '2026-09-21']);
        $cancelado = $this->crearExamen(['fecha' => '2026-09-22', 'estado' => 'cancelado']);
        $anulado = $this->crearExamen(['fecha' => '2026-09-23', 'estado' => 'anulado']);
        $suspendido = $this->crearExamen(['fecha' => '2026-09-24', 'estado' => 'suspendido']);

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes');

        $respuesta->assertOk()
            ->assertJsonPath('examenes.data.0.id_examen', $programado->id_examen)
            ->assertJsonPath('examenes.data.0.estado_actual', 'programado')
            ->assertJsonPath('examenes.data.1.id_examen', $cancelado->id_examen)
            ->assertJsonPath('examenes.data.1.estado_actual', 'cancelado')
            ->assertJsonPath('examenes.data.2.id_examen', $anulado->id_examen)
            ->assertJsonPath('examenes.data.2.estado_actual', 'anulado')
            ->assertJsonPath('examenes.data.3.id_examen', $suspendido->id_examen)
            // Suspendido es un estado de gestión propio: se muestra como tal,
            // aunque su ciclo (estado_horario) siga siendo programado.
            ->assertJsonPath('examenes.data.3.estado', 'suspendido')
            ->assertJsonPath('examenes.data.3.estado_actual', 'suspendido');
    }

    public function test_el_listado_filtra_por_estado(): void
    {
        // Ahora es 2026-09-20 10:00 (setUp).
        $programado = $this->crearExamen(['fecha' => '2026-09-21']);
        $enCurso = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]);
        $finalizado = $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]);
        $cancelado = $this->crearExamen(['fecha' => '2026-09-22', 'estado' => 'cancelado']);
        $anulado = $this->crearExamen(['fecha' => '2026-09-23', 'estado' => 'anulado']);
        $suspendido = $this->crearExamen(['fecha' => '2026-09-24', 'estado' => 'suspendido']);
        $admin = $this->usuario('administrador');

        $casos = [
            'programado' => [$programado->id_examen],
            'en_curso' => [$enCurso->id_examen],
            'finalizado' => [$finalizado->id_examen],
            'cancelado' => [$cancelado->id_examen],
            'anulado' => [$anulado->id_examen],
            'suspendido' => [$suspendido->id_examen],
        ];

        foreach ($casos as $estado => $esperados) {
            $respuesta = $this->actingAs($admin)->get('/examenes?estado='.$estado);

            $respuesta->assertOk();
            $vistos = collect($respuesta->json('examenes.data'))->pluck('id_examen')->all();

            $this->assertEqualsCanonicalizing($esperados, $vistos, "El filtro estado={$estado} debería devolver solo el examen esperado.");
        }

        // "En curso" y "Suspendido" no se mezclan: el examen en curso no es suspendido,
        // y el suspendido (aunque su ventana esté activa) cae solo en su chip.
        $respuesta = $this->actingAs($admin)->get('/examenes?estado=suspendido');

        $vistos = collect($respuesta->json('examenes.data'))->pluck('id_examen')->all();
        $this->assertNotContains($enCurso->id_examen, $vistos);
    }

    public function test_el_listado_incluye_conteos_por_estado_para_los_chips(): void
    {
        $this->crearExamen(['fecha' => '2026-09-21']); // programado
        $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '09:30', 'duracion_minutos' => 120]); // en curso
        $this->crearExamen(['fecha' => '2026-09-20', 'hora_inicio' => '08:00', 'duracion_minutos' => 90]); // finalizado
        $this->crearExamen(['fecha' => '2026-09-22', 'estado' => 'cancelado']); // cancelado
        $this->crearExamen(['fecha' => '2026-09-23', 'estado' => 'anulado']); // anulado
        $this->crearExamen(['fecha' => '2026-09-24', 'estado' => 'suspendido']); // suspendido

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes');

        $respuesta->assertOk()
            ->assertJsonPath('conteos.programado', 1)
            ->assertJsonPath('conteos.en_curso', 1)
            ->assertJsonPath('conteos.finalizado', 1)
            ->assertJsonPath('conteos.cancelado', 1)
            ->assertJsonPath('conteos.anulado', 1)
            ->assertJsonPath('conteos.suspendido', 1);
    }
}
