<?php

namespace Tests\Integration;

use App\Models\Habilitacion;
use App\Models\RegistroIngreso;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class HabilitacionTest extends TestCase
{
    use BuildsScenario;
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->data = $this->makeExamScenario(3);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enable(int $index): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante' => $this->data['students'][$index]->id_estudiante,
            'id_examen' => $this->data['exam']->id_examen,
        ]);
    }

    private function registerEntry(int $index): void
    {
        RegistroIngreso::create([
            'id_estudiante' => $this->data['students'][$index]->id_estudiante,
            'id_examen_ambiente' => $this->data['examRoom']->id_examen_ambiente,
            'id_usuario' => $this->makeUser('personal de control de ingreso')->id,
            'fecha_hora_ingreso' => Carbon::now(),
        ]);
    }

    public function test_admin_links_students_to_an_exam_and_repeating_does_not_duplicate(): void
    {
        $admin = $this->makeUser('administrador');
        $url = '/examenes/'.$this->data['exam']->id_examen.'/habilitaciones';
        $ids = [$this->data['students'][0]->id_estudiante, $this->data['students'][1]->id_estudiante];

        $this->actingAs($admin)->post($url, ['student_ids' => $ids])
            ->assertSessionHas('success', 'Asociación completada. Nuevos: 2, ya existentes: 0.');
        $this->actingAs($admin)->post($url, ['student_ids' => $ids])
            ->assertSessionHas('success', 'Asociación completada. Nuevos: 0, ya existentes: 2.');

        $this->assertSame(2, Habilitacion::where('id_examen', $this->data['exam']->id_examen)->count());
    }

    public function test_linking_needs_at_least_one_existing_student(): void
    {
        $admin = $this->makeUser('administrador');
        $url = '/examenes/'.$this->data['exam']->id_examen.'/habilitaciones';

        $this->actingAs($admin)->post($url, ['student_ids' => []])->assertSessionHasErrors('student_ids');
        $this->actingAs($admin)->post($url, ['student_ids' => [999999]])->assertSessionHasErrors('student_ids.0');
    }

    public function test_teacher_cannot_link_students_outside_the_own_groups(): void
    {
        $outsider = $this->makeStudent('202699999', '9999999');

        $this->actingAs($this->data['teacher'])
            ->post('/examenes/'.$this->data['exam']->id_examen.'/habilitaciones', ['student_ids' => [$outsider->id_estudiante]])
            ->assertForbidden();
    }

    public function test_disabling_a_student_requires_a_reason(): void
    {
        $habilitacion = $this->enable(0);

        $this->actingAs($this->makeUser('administrador'))
            ->patchJson('/habilitaciones/'.$habilitacion->id_habilitacion, ['estado_habilitado' => false])
            ->assertJsonValidationErrors('motivo_inhabilitacion');

        $this->assertTrue($habilitacion->fresh()->estado_habilitado);
    }

    public function test_disabling_saves_reason_and_rules_and_writes_an_audit_log(): void
    {
        $admin = $this->makeUser('administrador');
        $habilitacion = $this->enable(0);

        $this->actingAs($admin)->patch('/habilitaciones/'.$habilitacion->id_habilitacion, [
            'estado_habilitado' => false,
            'motivo_inhabilitacion' => '  Deuda en biblioteca  ',
            'normas_particulares' => 'Sin calculadora',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('habilitacion', [
            'id_habilitacion' => $habilitacion->id_habilitacion,
            'estado_habilitado' => false,
            'motivo_inhabilitacion' => 'Deuda en biblioteca',
            'normas_particulares' => 'Sin calculadora',
        ]);
        $this->assertDatabaseHas('auditoria_log', [
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'habilitacion',
            'id_registro_afectado' => $habilitacion->id_habilitacion,
            'accion' => 'UPDATE',
        ]);
    }

    public function test_enabling_again_clears_the_reason(): void
    {
        $admin = $this->makeUser('administrador');
        $habilitacion = $this->enable(0);
        $url = '/habilitaciones/'.$habilitacion->id_habilitacion;

        $this->actingAs($admin)->patch($url, ['estado_habilitado' => false, 'motivo_inhabilitacion' => 'Deuda']);
        $this->actingAs($admin)->patch($url, ['estado_habilitado' => true]);

        $fresh = $habilitacion->fresh();
        $this->assertTrue($fresh->estado_habilitado);
        $this->assertNull($fresh->motivo_inhabilitacion);
    }

    public function test_status_cannot_change_after_the_student_entered(): void
    {
        $habilitacion = $this->enable(0);
        $this->registerEntry(0);

        $this->actingAs($this->makeUser('administrador'))
            ->patch('/habilitaciones/'.$habilitacion->id_habilitacion, [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'Tarde',
            ])
            ->assertSessionHasErrors('estado_habilitado');

        $this->assertTrue($habilitacion->fresh()->estado_habilitado);
    }

    public function test_teacher_cannot_change_a_student_of_another_teacher_group(): void
    {
        $otherTeacher = $this->makeUser('docente', 'docente_otro');
        $otherGroupData = $this->makeExamScenario(1, $otherTeacher);
        $foreign = Habilitacion::create([
            'id_estudiante' => $otherGroupData['students'][0]->id_estudiante,
            'id_examen' => $this->data['exam']->id_examen,
        ]);

        $this->actingAs($this->data['teacher'])
            ->patch('/habilitaciones/'.$foreign->id_habilitacion, [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'Prueba',
            ])
            ->assertForbidden();
    }

    public function test_bulk_disable_skips_students_who_already_entered(): void
    {
        foreach ([0, 1, 2] as $index) {
            $this->enable($index);
        }
        $this->registerEntry(0);

        $this->actingAs($this->makeUser('administrador'))
            ->patchJson('/examenes/'.$this->data['exam']->id_examen.'/habilitaciones', [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'Sancion',
                'estado' => 'habilitados',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Actualizados: 2. Omitidos por ingreso registrado: 1.');

        $this->assertSame(2, Habilitacion::where('estado_habilitado', false)->count());
        $this->assertSame(1, Habilitacion::where('estado_habilitado', true)->count());
    }

    public function test_bulk_action_must_match_the_selected_filter(): void
    {
        $this->enable(0);

        $this->actingAs($this->makeUser('administrador'))
            ->patchJson('/examenes/'.$this->data['exam']->id_examen.'/habilitaciones', [
                'estado_habilitado' => true,
                'estado' => 'habilitados',
            ])
            ->assertJsonValidationErrors('estado');
    }

    public function test_bulk_disable_requires_a_reason(): void
    {
        $this->enable(0);

        $this->actingAs($this->makeUser('administrador'))
            ->patchJson('/examenes/'.$this->data['exam']->id_examen.'/habilitaciones', [
                'estado_habilitado' => false,
                'estado' => 'habilitados',
            ])
            ->assertJsonValidationErrors('motivo_inhabilitacion');
    }

    public function test_exam_detail_lists_the_students_with_reason_and_entry_flag(): void
    {
        $first = $this->enable(0);
        $this->enable(1);
        $first->update(['estado_habilitado' => false, 'motivo_inhabilitacion' => 'Deuda']);
        $this->registerEntry(1);

        $response = $this->actingAs($this->makeUser('administrador'))
            ->getJson('/examenes/'.$this->data['exam']->id_examen.'/habilitaciones')
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertCount(2, $rows);
        $this->assertSame('Deuda', $rows->firstWhere('id_habilitacion', $first->id_habilitacion)['motivo_inhabilitacion']);
        $this->assertTrue((bool) $rows->firstWhere('id_estudiante', $this->data['students'][1]->id_estudiante)['estudiante']['ya_ingreso']);
    }
}