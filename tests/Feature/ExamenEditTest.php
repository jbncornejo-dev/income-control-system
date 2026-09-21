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

class ExamenEditTest extends TestCase
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

    /**
     * @return array{examen: Examen, ambiente: Ambiente}
     */
    private function crearExamenConAmbiente(): array
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Edición '.uniqid()]);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula Edición '.uniqid(), 'capacidad' => 40]);

        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDays(5)->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
            'normas_generales' => 'Normas del examen',
        ]);

        ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);

        return ['examen' => $examen, 'ambiente' => $ambiente];
    }

    public function test_administrador_abre_la_pagina_de_edicion_con_datos_precargados(): void
    {
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get("/examenes/{$examen->id_examen}/editar");

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Examenes/Create')
            ->where('examen.id_examen', $examen->id_examen)
            ->where('examen.id_asignatura', $examen->id_asignatura)
            ->where('examen.fecha', $examen->fecha)
            ->where('examen.hora_inicio', '10:00:00')
            ->where('examen.duracion_minutos', 90)
            ->where('examen.examenes_ambientes.0.id_ambiente', $creado['ambiente']->id_ambiente));
    }

    public function test_docente_abre_la_edicion_de_un_examen_de_una_asignatura_que_dicta(): void
    {
        $creado = $this->crearExamenConAmbiente();
        $examen = $creado['examen'];

        $docente = $this->usuario('docente');
        Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/editar");

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Examenes/Create')
            ->where('examen.id_examen', $examen->id_examen)
            // El docente solo ve las asignaturas que dicta.
            ->has('asignaturas', 1)
            ->where('asignaturas.0.id_asignatura', $examen->id_asignatura));
    }

    public function test_docente_no_puede_abrir_la_edicion_de_un_examen_que_no_dicta(): void
    {
        $creado = $this->crearExamenConAmbiente();

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->get("/examenes/{$creado['examen']->id_examen}/editar");

        $respuesta->assertForbidden();
    }
}
