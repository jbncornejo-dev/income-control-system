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

class AmbienteUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Ambiente $ambiente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }

    public function test_updates_name_and_capacity_without_changing_id_or_exam_relations(): void
    {
        $otra = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 80]);
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '10:00', 'duracion_minutos' => 60,
        ]);
        $relacion = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $this->ambiente->id_ambiente]);
        $datosRelacion = $relacion->fresh()->getAttributes();
        $datosExamen = $examen->fresh()->getAttributes();

        $this->actingAs(User::factory()->create())->from('/ambientes')
            ->patch(route('ambientes.update', $this->ambiente), [
                'nombre_ambiente' => '  Laboratorio  ', 'capacidad' => 20, 'id_ambiente' => $otra->id_ambiente,
            ])->assertRedirect('/ambientes')->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Ambiente actualizado correctamente.');

        $this->assertDatabaseHas('ambiente', ['id_ambiente' => $this->ambiente->id_ambiente, 'nombre_ambiente' => 'Laboratorio', 'capacidad' => 20]);
        $this->assertSame($datosRelacion, $relacion->fresh()->getAttributes());
        $this->assertSame($datosExamen, $examen->fresh()->getAttributes());
        $this->assertSame('Aula 102', $otra->fresh()->nombre_ambiente);
        $this->assertDatabaseCount('ambiente', 2);
    }

    public function test_accepts_unchanged_values(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('ambientes.update', $this->ambiente), ['nombre_ambiente' => '  Aula 101  ', 'capacidad' => 40])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertOriginalRoom();
    }

    public function test_rejects_duplicate_even_when_payload_contains_other_room_id(): void
    {
        $otra = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 80]);
        $this->actingAs(User::factory()->create())->from('/ambientes')
            ->patch(route('ambientes.update', $this->ambiente), [
                'nombre_ambiente' => '  Aula 102  ', 'capacidad' => 100, 'id_ambiente' => $otra->id_ambiente,
            ])->assertRedirect('/ambientes')->assertSessionHasErrors([
                'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
            ]);
        $this->assertOriginalRoom();
        $this->assertDatabaseHas('ambiente', ['id_ambiente' => $otra->id_ambiente, 'nombre_ambiente' => 'Aula 102', 'capacidad' => 80]);
    }

    #[DataProvider('invalidData')]
    public function test_rejects_invalid_data_without_changes(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->create())->patchJson(route('ambientes.update', $this->ambiente), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertOriginalRoom();
    }

    public static function invalidData(): array
    {
        $cases = [
            'missing name' => [['capacidad' => 50], 'nombre_ambiente'],
            'missing capacity' => [['nombre_ambiente' => 'Laboratorio'], 'capacidad'],
        ];
        foreach (['null' => null, 'empty' => '', 'spaces' => '  ', 'number' => 123, 'array' => ['Aula'], 'long' => str_repeat('á', 101)] as $label => $name) {
            $cases['name '.$label] = [['nombre_ambiente' => $name, 'capacidad' => 50], 'nombre_ambiente'];
        }
        foreach (['null' => null, 'empty' => '', 'zero' => 0, 'negative' => -1, 'decimal' => 1.5, 'text' => 'muchos', 'array' => [40], 'overflow' => 2147483648] as $label => $capacity) {
            $cases['capacity '.$label] = [['nombre_ambiente' => 'Laboratorio', 'capacidad' => $capacity], 'capacidad'];
        }

        return $cases;
    }

    #[DataProvider('validLimits')]
    public function test_accepts_valid_limits(int|string $capacity): void
    {
        $name = str_repeat('á', 100);
        $this->actingAs(User::factory()->create())
            ->patch(route('ambientes.update', $this->ambiente), ['nombre_ambiente' => $name, 'capacidad' => $capacity])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('ambiente', ['id_ambiente' => $this->ambiente->id_ambiente, 'nombre_ambiente' => $name, 'capacidad' => (int) $capacity]);
    }

    public static function validLimits(): array
    {
        return ['minimum' => [1], 'maximum' => [2147483647], 'HTML form' => ['50']];
    }

    public function test_duplicate_created_after_validation_does_not_partially_update_room(): void
    {
        $this->actingAs(User::factory()->create());
        $inserted = false;
        // Reproducir un registro del mismo nombre entre la validación y la actualización.
        DB::listen(function (QueryExecuted $query) use (&$inserted) {
            if (! $inserted && str_starts_with($query->sql, 'select count(*)') && str_contains($query->sql, 'from "ambiente"')) {
                $inserted = true;
                Ambiente::create(['nombre_ambiente' => 'Laboratorio', 'capacidad' => 80]);
            }
        });
        $this->from('/ambientes')->patch(route('ambientes.update', $this->ambiente), ['nombre_ambiente' => 'Laboratorio', 'capacidad' => 100])
            ->assertRedirect('/ambientes')->assertSessionHasErrors([
                'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
            ])->assertSessionHasInput('nombre_ambiente', 'Laboratorio');
        $this->assertTrue($inserted);
        $this->assertOriginalRoom();
        $this->assertDatabaseCount('ambiente', 2);
    }

    public function test_nonexistent_room_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('ambientes.update', 99999), ['nombre_ambiente' => 'Laboratorio', 'capacidad' => 50])
            ->assertNotFound();
        $this->assertOriginalRoom();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->patch(route('ambientes.update', $this->ambiente), ['nombre_ambiente' => 'Laboratorio', 'capacidad' => 50])
            ->assertRedirect(route('login'));
        $this->assertOriginalRoom();
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->patch(route('ambientes.update', $this->ambiente), ['nombre_ambiente' => 'Laboratorio', 'capacidad' => 50])
            ->assertForbidden();
        $this->assertOriginalRoom();
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }

    private function assertOriginalRoom(): void
    {
        $this->assertDatabaseHas('ambiente', ['id_ambiente' => $this->ambiente->id_ambiente, 'nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }
}
