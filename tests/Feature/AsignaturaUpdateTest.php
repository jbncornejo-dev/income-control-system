<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Asignatura $asignatura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
    }

    public function test_administrador_updates_only_name_of_subject_identified_by_url(): void
    {
        $otra = Asignatura::create(['nombre_asignatura' => 'Física']);
        $id = $this->asignatura->id_asignatura;

        $response = $this->actingAs(User::factory()->create())
            ->from('/asignaturas')->patch(route('asignaturas.update', $this->asignatura), [
                'nombre_asignatura' => '  Cálculo II  ',
                'id_asignatura' => $otra->id_asignatura,
            ]);

        $response->assertRedirect('/asignaturas')->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Asignatura actualizada correctamente.');
        $this->assertDatabaseHas('asignatura', ['id_asignatura' => $id, 'nombre_asignatura' => 'Cálculo II']);
        $this->assertSame('Física', $otra->fresh()->nombre_asignatura);
        $this->assertDatabaseCount('asignatura', 2);
    }

    public function test_renaming_subject_preserves_its_related_exam(): void
    {
        $examen = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
        $datosExamen = $examen->fresh()->getAttributes();

        $this->actingAs(User::factory()->create())
            ->patch(route('asignaturas.update', $this->asignatura), ['nombre_asignatura' => 'Cálculo II'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($datosExamen, $examen->fresh()->getAttributes());
        $this->assertSame('Cálculo II', $examen->fresh()->asignatura->nombre_asignatura);
        $this->assertDatabaseCount('asignatura', 1);
        $this->assertDatabaseCount('examen', 1);
    }

    public function test_accepts_unchanged_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('asignaturas.update', $this->asignatura), ['nombre_asignatura' => '  Cálculo I  '])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('Cálculo I', $this->asignatura->fresh()->nombre_asignatura);
        $this->assertDatabaseCount('asignatura', 1);
    }

    public function test_rejects_other_subject_name_even_if_payload_contains_its_id(): void
    {
        $otra = Asignatura::create(['nombre_asignatura' => 'Física']);

        $this->actingAs(User::factory()->create())
            ->from('/asignaturas')->patch(route('asignaturas.update', $this->asignatura), [
                'nombre_asignatura' => '  Física  ',
                'id_asignatura' => $otra->id_asignatura,
            ])->assertRedirect('/asignaturas')->assertSessionHasErrors([
                'nombre_asignatura' => 'Ya existe una asignatura con ese nombre',
            ]);

        $this->assertSame('Cálculo I', $this->asignatura->fresh()->nombre_asignatura);
        $this->assertSame('Física', $otra->fresh()->nombre_asignatura);
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_name_without_modifying_subject(array $payload): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('asignaturas.update', $this->asignatura), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('nombre_asignatura');

        $this->assertSame('Cálculo I', $this->asignatura->fresh()->nombre_asignatura);
    }

    public static function invalidNames(): array
    {
        return [
            'missing' => [[]],
            'null' => [['nombre_asignatura' => null]],
            'empty' => [['nombre_asignatura' => '']],
            'whitespace' => [['nombre_asignatura' => '   ']],
            'integer' => [['nombre_asignatura' => 123]],
            'boolean' => [['nombre_asignatura' => true]],
            'array' => [['nombre_asignatura' => ['Física']]],
            'too long' => [['nombre_asignatura' => str_repeat('á', 151)]],
        ];
    }

    public function test_accepts_150_character_name(): void
    {
        $name = str_repeat('á', 150);
        $this->actingAs(User::factory()->create())
            ->patch(route('asignaturas.update', $this->asignatura), ['nombre_asignatura' => $name])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($name, $this->asignatura->fresh()->nombre_asignatura);
    }

    public function test_nonexistent_subject_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->patchJson(route('asignaturas.update', 99999), ['nombre_asignatura' => 'Física'])
            ->assertNotFound();
        $this->assertDatabaseCount('asignatura', 1);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->patch(route('asignaturas.update', $this->asignatura), ['nombre_asignatura' => 'Física'])
            ->assertRedirect(route('login'));
        $this->assertSame('Cálculo I', $this->asignatura->fresh()->nombre_asignatura);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_cannot_update_subject(string $roleName): void
    {
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $rol->id_rol]);

        $this->actingAs($user)
            ->patch(route('asignaturas.update', $this->asignatura), ['nombre_asignatura' => 'Física'])
            ->assertForbidden();
        $this->assertSame('Cálculo I', $this->asignatura->fresh()->nombre_asignatura);
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
