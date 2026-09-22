<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamenCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
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

    private function crearGrupo(
        Asignatura $asignatura,
        string $nombreGrupo,
        ?User $docente = null
    ): Grupo {
        $docente ??= $this->usuario('docente', ' '.$nombreGrupo);

        return Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => $nombreGrupo,
        ]);
    }

    public function test_administrador_abre_la_pagina_de_registro_con_asignaturas_y_ambientes(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $periodo = $this->crearPeriodo();
        $grupo = $this->crearGrupo($asignatura, 'A');

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes/crear');

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Examenes/Create')
            ->where('asignaturas.0.id_asignatura', $asignatura->id_asignatura)
            ->where('asignaturas.0.nombre_asignatura', 'Cálculo I')
            ->where('asignaturas.0.grupos.0.id_grupo', $grupo->id_grupo)
            ->where('asignaturas.0.grupos.0.nombre_grupo', 'A')
            ->where('ambientes.0.id_ambiente', $ambiente->id_ambiente)
            ->where('ambientes.0.nombre_ambiente', 'Aula 101')
            ->where('periodos.0.id_periodo', $periodo->id_periodo)
            ->where('periodos.0.gestion', '2026')
            ->where('periodos.0.tipo', 'semestre')
            ->where('periodos.0.numero', 1));
    }

    public function test_docente_abre_la_pagina_y_solo_ve_los_grupos_que_dicta(): void
    {
        $propia = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $docente = $this->usuario('docente');
        $grupo = $this->crearGrupo($propia, 'A', $docente);
        // Grupo de otro docente en la misma asignatura: debe filtrarse.
        $otroDocente = $this->usuario('docente', '2');
        $this->crearGrupo($propia, 'B', $otroDocente);

        $respuesta = $this->actingAs($docente)->get('/examenes/crear');

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Examenes/Create')
            ->has('asignaturas', 1)
            ->where('asignaturas.0.id_asignatura', $propia->id_asignatura)
            ->where('asignaturas.0.nombre_asignatura', 'Cálculo I')
            ->has('asignaturas.0.grupos', 1)
            ->where('asignaturas.0.grupos.0.id_grupo', $grupo->id_grupo));
    }

    public function test_docente_abre_la_pagina_y_solo_ve_las_asignaturas_que_dicta(): void
    {
        $propia = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        Asignatura::create(['nombre_asignatura' => 'Física I']);
        $docente = $this->usuario('docente');
        Grupo::create([
            'id_asignatura' => $propia->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $respuesta = $this->actingAs($docente)->get('/examenes/crear');

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Examenes/Create')
            ->has('asignaturas', 1)
            ->where('asignaturas.0.id_asignatura', $propia->id_asignatura)
            ->where('asignaturas.0.nombre_asignatura', 'Cálculo I'));
    }

    public function test_registro_redirige_al_listado_con_mensaje_exitoso(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación I']);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 30]);
        $grupo = $this->crearGrupo($asignatura, 'A');

        $respuesta = $this->actingAs($this->usuario('administrador'))->post('/examenes', [
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
            'normas_generales' => 'Presentar documento de identidad.',
            'id_grupos' => [$grupo->id_grupo],
            'id_ambientes' => [$ambiente->id_ambiente],
        ]);

        $respuesta->assertRedirect(route('examenes.index'));
        $respuesta->assertSessionHas('success', 'Examen registrado correctamente.');
        $this->assertDatabaseHas('examen', ['id_asignatura' => $asignatura->id_asignatura]);
        $this->assertDatabaseHas('examen_ambiente', ['id_ambiente' => $ambiente->id_ambiente]);
        $this->assertDatabaseHas('examen_grupo', ['id_grupo' => $grupo->id_grupo]);
    }

    public function test_errores_de_validacion_quedan_disponibles_para_el_formulario(): void
    {
        Examen::create([
            'id_asignatura' => Asignatura::create(['nombre_asignatura' => 'Física'])->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ]);
        $examen = Examen::sole();
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 103', 'capacidad' => 25]);
        ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);
        $grupo = $this->crearGrupo(
            Asignatura::findOrFail($examen->id_asignatura),
            'A'
        );

        $this->actingAs($this->usuario('administrador'))->post('/examenes', [
            'id_asignatura' => $examen->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:30',
            'duracion_minutos' => 90,
            'id_grupos' => [$grupo->id_grupo],
            'id_ambientes' => [$ambiente->id_ambiente],
        ])->assertSessionHasErrors('id_ambientes');
    }
}
