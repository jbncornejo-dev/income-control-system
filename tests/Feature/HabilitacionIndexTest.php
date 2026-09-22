<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Ambiente;
use App\Models\AuditoriaLog;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Rol;
use App\Models\RegistroIngreso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HabilitacionIndexTest extends TestCase
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

    private function crearExamen(string $sufijo = ''): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Habilitaciones '.$sufijo.uniqid()]);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-21',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ]);
    }

    private function usuario(string $nombreRol): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($nombreRol),
            'username' => str($nombreRol)->slug('_'),
            'email' => str($nombreRol)->slug().'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    public function test_docente_ve_las_habilitaciones_de_un_examen_que_cubre_alguna_de_sus_grupos(): void
    {
        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');

        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'B',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_docente_no_ve_las_habilitaciones_de_un_examen_que_no_cubre_ninguna_de_sus_grupos(): void
    {
        $examen = $this->crearExamen();

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertForbidden();
    }

    public function test_administrador_accede_a_las_habilitaciones_de_cualquier_examen(): void
    {
        $examen = $this->crearExamen();

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_filtro_y_cambio_masivo_incluyen_todas_las_paginas(): void
    {
        $examen = $this->crearExamen();
        $admin = $this->usuario('administrador');
        for ($i = 1; $i <= 18; $i++) {
            $estudiante = Estudiante::create([
                'codigo_universitario' => 'B'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'documento_identidad' => 'D'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nombres' => 'Bloque',
                'apellidos' => 'Prueba',
            ]);
            Habilitacion::create(['id_examen' => $examen->id_examen, 'id_estudiante' => $estudiante->id_estudiante, 'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Pendiente']);
        }

        $this->actingAs($admin)->getJson("/examenes/{$examen->id_examen}/habilitaciones?estado=inhabilitados&busqueda=Bloque")
            ->assertOk()->assertJsonPath('total', 18)->assertJsonCount(15, 'data');

        $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/habilitaciones", [
            'estado' => 'inhabilitados', 'busqueda' => 'Bloque', 'estado_habilitado' => true,
        ])->assertSessionHas('success');

        $this->assertSame(18, Habilitacion::where('id_examen', $examen->id_examen)->where('estado_habilitado', true)->count());
        $this->assertSame(18, AuditoriaLog::where('tabla_afectada', 'habilitacion')->count());
    }

    public function test_inhabilitacion_masiva_exige_motivo_y_docente_ajeno_no_puede_aplicarla(): void
    {
        $examen = $this->crearExamen();
        $url = "/examenes/{$examen->id_examen}/habilitaciones";
        $this->actingAs($this->usuario('administrador'))->patch($url, ['estado_habilitado' => false])
            ->assertSessionHasErrors('motivo_inhabilitacion');
        $this->actingAs($this->usuario('docente'))->patch($url, [
            'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Falta requisito',
        ])->assertForbidden();
    }

    public function test_cambio_masivo_omite_estudiantes_que_ya_ingresaron(): void
    {
        $examen = $this->crearExamen();
        $admin = $this->usuario('administrador');
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula '.uniqid(), 'capacidad' => 30]);
        $examenAmbiente = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);
        $estudiante = Estudiante::create(['codigo_universitario' => 'ING001', 'documento_identidad' => 'DOC001', 'nombres' => 'Ya', 'apellidos' => 'Ingresó']);
        $habilitacion = Habilitacion::create(['id_examen' => $examen->id_examen, 'id_estudiante' => $estudiante->id_estudiante]);
        RegistroIngreso::create(['id_estudiante' => $estudiante->id_estudiante, 'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente, 'id_usuario' => $admin->id]);

        $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/habilitaciones", [
            'estado' => 'habilitados', 'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Motivo común',
        ])->assertSessionHas('success', fn ($mensaje) => str_contains($mensaje, 'Omitidos por ingreso registrado: 1'));

        $this->assertTrue($habilitacion->fresh()->estado_habilitado);
        $this->actingAs($admin)->patch("/habilitaciones/{$habilitacion->id_habilitacion}", [
            'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Motivo individual',
        ])->assertSessionHasErrors('estado_habilitado');
    }

    public function test_cambio_masivo_rechaza_filtro_todos_y_accion_incompatible(): void
    {
        $examen = $this->crearExamen();
        $url = "/examenes/{$examen->id_examen}/habilitaciones";
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->patch($url, ['estado' => 'todos', 'estado_habilitado' => true])
            ->assertSessionHasErrors('estado');
        $this->actingAs($admin)->patch($url, ['estado' => 'habilitados', 'estado_habilitado' => true])
            ->assertSessionHasErrors('estado');
    }
}
