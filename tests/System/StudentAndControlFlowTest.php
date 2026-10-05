<?php

namespace Tests\System;

use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class StudentAndControlFlowTest extends TestCase
{
    use BuildsScenario;
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

    public function test_new_student_must_change_the_initial_password_before_seeing_the_exams(): void
    {
        $data = $this->makeExamScenario(0);
        $this->actingAs($this->makeUser('administrador'))->post('/estudiantes', [
            'codigo_universitario' => '202600055',
            'documento_identidad' => '5555555',
            'nombres' => 'Marta',
            'apellidos' => 'Lopez',
        ]);
        $student = \App\Models\Estudiante::where('codigo_universitario', '202600055')->firstOrFail();
        Inscripcion::create(['id_estudiante' => $student->id_estudiante, 'id_grupo' => $data['group']->id_grupo]);
        Habilitacion::create(['id_estudiante' => $student->id_estudiante, 'id_examen' => $data['exam']->id_examen]);
        $this->post('/logout');

        $this->post('/login', ['identificador' => '202600055', 'password' => '5555555'])
            ->assertRedirect(route('cambiar-password.show'));
        $this->get('/mis-examenes')->assertRedirect(route('cambiar-password.show'));

        $this->post('/cambiar-password', [
            'password_actual' => 'incorrecta',
            'password' => 'nuevaclave1',
            'password_confirmation' => 'nuevaclave1',
        ])->assertSessionHasErrors('password_actual');

        $this->post('/cambiar-password', [
            'password_actual' => '5555555',
            'password' => 'nuevaclave1',
            'password_confirmation' => 'nuevaclave1',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertRedirect(route('mis-examenes.index'));
        $this->get('/mis-examenes')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Estudiantes/MisExamenes')
            ->has('examenes', 1)
            ->where('examenes.0.id', $data['exam']->id_examen)
        );

        $this->post('/logout');
        $this->post('/login', ['identificador' => '202600055', 'password' => '5555555'])
            ->assertSessionHasErrors('identificador');
        $this->post('/login', ['identificador' => '202600055', 'password' => 'nuevaclave1'])
            ->assertRedirect('/dashboard');
    }

    public function test_student_cannot_reach_staff_areas(): void
    {
        $student = $this->makeUser('estudiante', 'estudiante');
        $this->actingAs($student);

        foreach (['/estudiantes', '/examenes', '/usuarios', '/incidencias', '/control/dashboard', '/admin/dashboard'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/incidencias', [])->assertForbidden();
    }

    public function test_control_staff_logs_in_and_registers_an_incident_that_the_admin_can_see(): void
    {
        $data = $this->makeExamScenario(1);
        $control = $this->makeUser('personal de control de ingreso', 'control');

        $this->post('/login', ['identificador' => 'control', 'password' => 'pass'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('control.dashboard'));
        $this->get('/control/dashboard')->assertOk();

        $this->post('/incidencias', [
            'id_examen' => $data['exam']->id_examen,
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'tipo_incidencia' => 'Problema de identificación',
            'descripcion_motivo' => '  Sin documento  ',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('incidencia', [
            'id_examen' => $data['exam']->id_examen,
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'id_usuario' => $control->id,
            'tipo_incidencia' => 'Problema de identificación',
            'descripcion_motivo' => 'Sin documento',
        ]);

        $this->get('/examenes')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();

        $this->post('/logout');
        $this->actingAs($this->makeUser('administrador'))->get('/incidencias')->assertOk();
    }

    public function test_incident_needs_a_valid_type_and_a_description(): void
    {
        $data = $this->makeExamScenario(1);
        $this->actingAs($this->makeUser('personal de control de ingreso', 'control'));

        $this->post('/incidencias', [
            'id_examen' => $data['exam']->id_examen,
            'tipo_incidencia' => 'Inventado',
            'descripcion_motivo' => 'Algo',
        ])->assertSessionHasErrors('tipo_incidencia');

        $this->post('/incidencias', [
            'id_examen' => $data['exam']->id_examen,
            'tipo_incidencia' => 'Otro',
            'descripcion_motivo' => '   ',
        ])->assertSessionHasErrors('descripcion_motivo');

        $this->assertSame(0, Incidencia::count());
    }
}