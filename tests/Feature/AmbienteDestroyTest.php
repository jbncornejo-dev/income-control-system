<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbienteDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function crearExamen(): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
    }

    public function test_administrador_deletes_only_room_without_exams(): void
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $otro = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 80]);
        $examen = $this->crearExamen();
        $relacion = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $otro->id_ambiente]);

        $this->actingAs(User::factory()->create())->delete(route('ambientes.destroy', $ambiente))
            ->assertRedirect(route('ambientes.index'))
            ->assertSessionHas('success', 'Ambiente eliminado correctamente.');

        $this->assertModelMissing($ambiente);
        $this->assertModelExists($otro);
        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
    }

    public function test_cannot_delete_room_with_exam_and_preserves_relationship(): void
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $examen = $this->crearExamen();
        $relacion = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);

        $this->actingAs(User::factory()->create())->from('/ambientes')
            ->delete(route('ambientes.destroy', $ambiente))
            ->assertRedirect('/ambientes')
            ->assertSessionHas('error', 'No se puede eliminar el ambiente porque tiene exámenes registrados')
            ->assertSessionMissing('success');

        $this->assertModelExists($ambiente);
        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
    }

    public function test_foreign_key_blocks_association_created_between_check_and_delete(): void
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $examen = $this->crearExamen();
        $this->actingAs(User::factory()->create());
        $relacion = null;

        // Asociar después de la consulta reproduce la ventana de carrera en PostgreSQL.
        DB::listen(function (QueryExecuted $query) use ($ambiente, $examen, &$relacion) {
            if ($relacion === null && str_starts_with($query->sql, 'select exists')
                && str_contains($query->sql, 'from "examen_ambiente"')) {
                $relacion = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);
            }
        });

        $this->from('/ambientes')->delete(route('ambientes.destroy', $ambiente))
            ->assertRedirect('/ambientes')
            ->assertSessionHas('error', 'No se puede eliminar el ambiente porque tiene exámenes registrados')
            ->assertSessionMissing('success');

        $this->assertNotNull($relacion);
        $this->assertModelExists($ambiente);
        $this->assertModelExists($examen);
        $this->assertModelExists($relacion);
    }

    public function test_nonexistent_room_returns_404(): void
    {
        $this->actingAs(User::factory()->create())->delete(route('ambientes.destroy', 99999))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $this->delete(route('ambientes.destroy', $ambiente))->assertRedirect(route('login'));
        $this->assertModelExists($ambiente);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->delete(route('ambientes.destroy', $ambiente))->assertForbidden();
        $this->assertModelExists($ambiente);
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }
}
