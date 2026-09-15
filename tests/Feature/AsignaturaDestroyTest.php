<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function crearExamen(Asignatura $asignatura): Examen
    {
        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
    }

    public function test_administrador_deletes_only_requested_subject_without_exams(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $otra = Asignatura::create(['nombre_asignatura' => 'Física']);
        $examen = $this->crearExamen($otra);

        $this->actingAs(User::factory()->create())
            ->delete(route('asignaturas.destroy', $asignatura))
            ->assertRedirect(route('asignaturas.index'))
            ->assertSessionHas('success', 'Asignatura eliminada correctamente.');

        $this->assertModelMissing($asignatura);
        $this->assertModelExists($otra);
        $this->assertModelExists($examen);
    }

    public function test_cannot_delete_subject_with_exams(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $examen = $this->crearExamen($asignatura);

        $this->actingAs(User::factory()->create())->from('/asignaturas')
            ->delete(route('asignaturas.destroy', $asignatura))
            ->assertRedirect('/asignaturas')
            ->assertSessionHas('error', 'No se puede eliminar la asignatura porque tiene exámenes registrados')
            ->assertSessionMissing('success');

        $this->assertModelExists($asignatura);
        $this->assertModelExists($examen);
    }

    public function test_foreign_key_blocks_exam_created_between_check_and_delete(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $this->actingAs(User::factory()->create());
        $examen = null;

        // Reproduce la ventana de carrera insertando después de consultar relaciones,
        // antes del DELETE, para comprobar la restricción real de PostgreSQL.
        DB::listen(function (QueryExecuted $query) use ($asignatura, &$examen) {
            if ($examen === null && str_starts_with($query->sql, 'select exists')
                && str_contains($query->sql, 'from "examen"')) {
                $examen = $this->crearExamen($asignatura);
            }
        });

        $this->from('/asignaturas')->delete(route('asignaturas.destroy', $asignatura))
            ->assertRedirect('/asignaturas')
            ->assertSessionHas('error', 'No se puede eliminar la asignatura porque tiene exámenes registrados')
            ->assertSessionMissing('success');

        $this->assertNotNull($examen);
        $this->assertModelExists($asignatura);
        $this->assertModelExists($examen);
    }

    public function test_nonexistent_subject_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->delete(route('asignaturas.destroy', 99999))->assertNotFound();
    }

    public function test_guest_cannot_delete_subject(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);

        $this->delete(route('asignaturas.destroy', $asignatura))->assertRedirect(route('login'));
        $this->assertModelExists($asignatura);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_cannot_delete_subject(string $roleName): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $rol->id_rol]);

        $this->actingAs($user)->delete(route('asignaturas.destroy', $asignatura))->assertForbidden();
        $this->assertModelExists($asignatura);
    }

    public static function nonAdministratorRoles(): array
    {
        return [
            'docente' => ['docente'],
            'control' => ['personal de control de ingreso'],
            'estudiante' => ['estudiante'],
        ];
    }
}
