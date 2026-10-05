<?php

namespace Tests\Integration;

use App\Models\AuditoriaLog;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\RegistroIngreso;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use BuildsScenario;
    use RefreshDatabase;

    private function assertRejectedByDatabase(callable $action): void
    {
        try {
            DB::transaction($action);
            $this->fail('The database accepted an operation that should be rejected');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_all_model_tables_exist(): void
    {
        $tables = [
            'rol', 'users', 'estudiante', 'asignatura', 'ambiente', 'examen', 'examen_ambiente',
            'habilitacion', 'registro_ingreso', 'incidencia', 'auditoria_log', 'grupo', 'inscripcion',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    public function test_foreign_keys_point_to_the_expected_parent_tables(): void
    {
        $rows = DB::select("
            select conrelid::regclass::text as child, confrelid::regclass::text as parent
            from pg_constraint
            where contype = 'f' and connamespace = 'public'::regnamespace
        ");
        $found = collect($rows)->map(fn ($row) => $row->child.'>'.$row->parent)->all();

        $expected = [
            'habilitacion>estudiante', 'habilitacion>examen',
            'registro_ingreso>estudiante', 'registro_ingreso>examen_ambiente', 'registro_ingreso>users',
            'examen_ambiente>examen', 'examen_ambiente>ambiente',
            'incidencia>examen', 'incidencia>estudiante', 'incidencia>users',
            'auditoria_log>users', 'users>rol',
        ];

        foreach ($expected as $foreignKey) {
            $this->assertContains($foreignKey, $found);
        }
    }

    public function test_student_code_and_document_are_unique(): void
    {
        $this->makeStudent('202600001', '1000001');

        $this->assertRejectedByDatabase(fn () => $this->makeStudent('202600001', '1000002'));
        $this->assertRejectedByDatabase(fn () => $this->makeStudent('202600002', '1000001'));
    }

    public function test_a_student_cannot_be_enabled_twice_for_the_same_exam(): void
    {
        $data = $this->makeExamScenario(1);
        Habilitacion::create(['id_estudiante' => $data['students'][0]->id_estudiante, 'id_examen' => $data['exam']->id_examen]);

        $this->assertRejectedByDatabase(fn () => Habilitacion::create([
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'id_examen' => $data['exam']->id_examen,
        ]));
    }

    public function test_a_student_cannot_enter_the_same_exam_room_twice(): void
    {
        $data = $this->makeExamScenario(1);
        $control = $this->makeUser('personal de control de ingreso');
        $entry = [
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'id_examen_ambiente' => $data['examRoom']->id_examen_ambiente,
            'id_usuario' => $control->id,
            'fecha_hora_ingreso' => Carbon::now(),
        ];
        RegistroIngreso::create($entry);

        $this->assertRejectedByDatabase(fn () => RegistroIngreso::create($entry));
    }

    public function test_deleting_an_exam_removes_its_dependent_rows(): void
    {
        $data = $this->makeExamScenario(1);
        $examId = $data['exam']->id_examen;
        Habilitacion::create(['id_estudiante' => $data['students'][0]->id_estudiante, 'id_examen' => $examId]);
        $data['exam']->delete();

        $this->assertDatabaseMissing('habilitacion', ['id_examen' => $examId]);
        $this->assertDatabaseMissing('examen_ambiente', ['id_examen' => $examId]);
    }

    public function test_a_student_with_entry_records_cannot_be_deleted(): void
    {
        $data = $this->makeExamScenario(1);
        $control = $this->makeUser('personal de control de ingreso');
        RegistroIngreso::create([
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'id_examen_ambiente' => $data['examRoom']->id_examen_ambiente,
            'id_usuario' => $control->id,
            'fecha_hora_ingreso' => Carbon::now(),
        ]);

        $this->assertRejectedByDatabase(fn () => $data['students'][0]->delete());
    }

    public function test_a_room_used_by_an_exam_cannot_be_deleted(): void
    {
        $data = $this->makeExamScenario(1);

        $this->assertRejectedByDatabase(fn () => $data['room']->delete());
    }

    public function test_audit_log_keeps_rows_when_the_user_is_removed(): void
    {
        $admin = $this->makeUser('administrador');
        $log = AuditoriaLog::create([
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'habilitacion',
            'id_registro_afectado' => 1,
            'accion' => 'UPDATE',
        ]);

        $admin->delete();

        $this->assertNull($log->fresh()->id_usuario);
    }

    public function test_incidents_are_removed_with_their_exam_but_keep_going_without_the_student(): void
    {
        $data = $this->makeExamScenario(1);
        $control = $this->makeUser('personal de control de ingreso');
        $incident = Incidencia::create([
            'id_examen' => $data['exam']->id_examen,
            'id_estudiante' => $data['students'][0]->id_estudiante,
            'id_usuario' => $control->id,
            'tipo_incidencia' => 'Otro',
            'descripcion_motivo' => 'Prueba',
            'fecha_hora' => Carbon::now(),
        ]);

        $data['students'][0]->delete();
        $this->assertNull($incident->fresh()->id_estudiante);

        $data['exam']->delete();
        $this->assertDatabaseMissing('incidencia', ['id_incidencia' => $incident->id_incidencia]);
    }
}