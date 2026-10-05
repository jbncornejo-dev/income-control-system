<?php

namespace Tests\System;

use App\Models\Examen;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class TeacherFlowTest extends TestCase
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

    private function examPayload(array $data, array $overrides = []): array
    {
        return array_merge([
            'id_asignatura' => $data['subject']->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-22',
            'hora_inicio' => '08:00',
            'duracion_minutos' => 60,
            'id_grupos' => [$data['group']->id_grupo],
            'id_ambientes' => [$data['room']->id_ambiente],
        ], $overrides);
    }

    public function test_teacher_logs_in_lands_on_the_teacher_dashboard_and_is_kept_out_of_admin_areas(): void
    {
        $this->makeUser('docente', 'docente');

        $this->post('/login', ['identificador' => 'docente', 'password' => 'pass'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('docente.dashboard'));
        $this->get('/docente/dashboard')->assertOk();

        foreach (['/examenes', '/estudiantes', '/incidencias'] as $allowed) {
            $this->get($allowed)->assertOk();
        }

        foreach (['/usuarios', '/ambientes', '/asignaturas', '/tipos-examen', '/admin/dashboard'] as $forbidden) {
            $this->get($forbidden)->assertForbidden();
        }

        $this->post('/estudiantes', [
            'codigo_universitario' => '202600099',
            'documento_identidad' => '9999999',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ])->assertForbidden();
    }

    public function test_teacher_registers_an_exam_for_own_group_and_students_are_enabled_automatically(): void
    {
        $data = $this->makeExamScenario(2);
        Examen::query()->delete();

        $this->actingAs($data['teacher'])
            ->post('/examenes', $this->examPayload($data))
            ->assertSessionHas('success');

        $exam = Examen::firstOrFail();
        $this->assertSame('09:00', $exam->hora_fin);
        $this->assertSame(2, $exam->habilitaciones()->count());
    }

    public function test_teacher_cannot_register_an_exam_for_another_teacher_group(): void
    {
        $data = $this->makeExamScenario(1);
        $other = $this->makeExamScenario(1, $this->makeUser('docente', 'docente_otro'));
        Examen::query()->delete();

        $payload = $this->examPayload($data, ['id_grupos' => [$other['group']->id_grupo]]);

        $this->actingAs($data['teacher'])->post('/examenes', $payload)->assertSessionHasErrors('id_grupos');
        $this->assertSame(0, Examen::count());
    }

    public function test_exam_in_the_past_or_with_invalid_duration_is_rejected(): void
    {
        $data = $this->makeExamScenario(1);
        Examen::query()->delete();
        $this->actingAs($data['teacher']);

        $this->post('/examenes', $this->examPayload($data, ['fecha' => '2026-09-19']))
            ->assertSessionHasErrors('fecha');
        $this->post('/examenes', $this->examPayload($data, ['duracion_minutos' => 0]))
            ->assertSessionHasErrors('duracion_minutos');
        $this->post('/examenes', $this->examPayload($data, ['duracion_minutos' => -30]))
            ->assertSessionHasErrors('duracion_minutos');

        $this->assertSame(0, Examen::count());
    }

    public function test_two_exams_cannot_use_the_same_room_at_overlapping_times(): void
    {
        $data = $this->makeExamScenario(1);
        Examen::query()->delete();
        $this->actingAs($data['teacher']);

        $this->post('/examenes', $this->examPayload($data))->assertSessionHas('success');
        $this->post('/examenes', $this->examPayload($data, ['hora_inicio' => '08:30']))
            ->assertSessionHasErrors();

        $this->assertSame(1, Examen::count());
    }
}