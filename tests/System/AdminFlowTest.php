<?php

namespace Tests\System;

use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use BuildsScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seeded_admin_logs_in_and_reaches_every_module(): void
    {
        $this->seed();

        $this->post('/login', ['identificador' => 'admin', 'password' => 'pass'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));

        foreach (['/admin/dashboard', '/estudiantes', '/examenes', '/usuarios', '/asignaturas', '/ambientes', '/incidencias'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guest_is_sent_to_login_and_wrong_credentials_are_rejected(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $this->makeUser('administrador', 'admin');
        $this->post('/login', ['identificador' => 'admin', 'password' => 'wrong'])
            ->assertSessionHasErrors('identificador');
        $this->assertGuest();
    }

    public function test_admin_sets_up_an_exam_from_scratch_and_controls_enrollment(): void
    {
        $this->makeUser('administrador', 'admin');
        $this->post('/login', ['identificador' => 'admin', 'password' => 'pass'])->assertRedirect('/dashboard');

        $this->post('/asignaturas', ['nombre_asignatura' => 'Calculo I']);
        $this->post('/ambientes', ['nombre_ambiente' => 'Aula 202', 'capacidad' => 50]);
        $this->assertDatabaseHas('asignatura', ['nombre_asignatura' => 'Calculo I']);
        $this->assertDatabaseHas('ambiente', ['nombre_ambiente' => 'Aula 202', 'capacidad' => 50]);

        $teacherRole = Rol::firstOrCreate(['nombre_rol' => 'docente']);
        $this->post('/usuarios', [
            'name' => 'Docente Uno',
            'email' => 'docente1@example.com',
            'username' => 'docente1',
            'id_rol' => $teacherRole->id_rol,
            'password' => 'password123',
        ]);
        $teacher = User::where('username', 'docente1')->firstOrFail();

        foreach ([['202600011', '1234567'], ['202600012', '7654321']] as [$code, $document]) {
            $this->post('/estudiantes', [
                'codigo_universitario' => $code,
                'documento_identidad' => $document,
                'nombres' => 'Luis',
                'apellidos' => 'Rojas',
            ]);
            $this->assertDatabaseHas('users', ['username' => $code]);
        }

        $subject = \App\Models\Asignatura::where('nombre_asignatura', 'Calculo I')->firstOrFail();
        $room = \App\Models\Ambiente::where('nombre_ambiente', 'Aula 202')->firstOrFail();
        $group = Grupo::create([
            'id_asignatura' => $subject->id_asignatura,
            'id_usuario' => $teacher->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        foreach (\App\Models\Estudiante::all() as $student) {
            Inscripcion::create(['id_estudiante' => $student->id_estudiante, 'id_grupo' => $group->id_grupo]);
        }

        $this->post('/examenes', [
            'id_asignatura' => $subject->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-21',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
            'id_grupos' => [$group->id_grupo],
            'id_ambientes' => [$room->id_ambiente],
        ])->assertSessionHas('success');

        $exam = \App\Models\Examen::firstOrFail();
        $this->assertSame('11:30', $exam->hora_fin);
        $this->assertSame(2, Habilitacion::where('id_examen', $exam->id_examen)->count());

        $first = Habilitacion::where('id_examen', $exam->id_examen)->orderBy('id_habilitacion')->firstOrFail();
        $this->patch('/habilitaciones/'.$first->id_habilitacion, [
            'estado_habilitado' => false,
            'motivo_inhabilitacion' => 'Documentos incompletos',
        ])->assertSessionHas('success');
        $this->assertFalse($first->fresh()->estado_habilitado);

        $control = $this->makeUser('personal de control de ingreso');
        RegistroIngreso::create([
            'id_estudiante' => $first->id_estudiante,
            'id_examen_ambiente' => $exam->examenesAmbientes()->firstOrFail()->id_examen_ambiente,
            'id_usuario' => $control->id,
            'fecha_hora_ingreso' => Carbon::now(),
        ]);

        $this->patch('/habilitaciones/'.$first->id_habilitacion, ['estado_habilitado' => true])
            ->assertSessionHasErrors('estado_habilitado');
        $this->assertFalse($first->fresh()->estado_habilitado);

        $this->delete('/examenes/'.$exam->id_examen)->assertSessionHas('error');
        $this->assertDatabaseHas('examen', ['id_examen' => $exam->id_examen]);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_admin_cannot_delete_own_account_or_duplicate_master_data(): void
    {
        $admin = $this->makeUser('administrador', 'admin');
        $this->actingAs($admin);

        $this->delete('/usuarios/'.$admin->id);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->post('/asignaturas', ['nombre_asignatura' => 'Fisica']);
        $this->post('/asignaturas', ['nombre_asignatura' => 'Fisica'])->assertSessionHasErrors('nombre_asignatura');
        $this->assertSame(1, \App\Models\Asignatura::where('nombre_asignatura', 'Fisica')->count());

        $this->post('/ambientes', ['nombre_ambiente' => 'Lab 1', 'capacidad' => 0])->assertSessionHasErrors('capacidad');
        $this->assertDatabaseMissing('ambiente', ['nombre_ambiente' => 'Lab 1']);
    }
}