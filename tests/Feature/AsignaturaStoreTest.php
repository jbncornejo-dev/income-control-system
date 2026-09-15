<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_can_register_a_subject_and_cannot_choose_its_id(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from('/dashboard')->post(route('asignaturas.store'), [
                'nombre_asignatura' => '  Cálculo I  ',
                'id_asignatura' => 99999,
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Asignatura registrada correctamente.');
        $this->assertDatabaseCount('asignatura', 1);
        $this->assertDatabaseHas('asignatura', ['nombre_asignatura' => 'Cálculo I']);
        $this->assertDatabaseMissing('asignatura', ['id_asignatura' => 99999]);
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_names(array $payload, string $message): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from('/dashboard')->post(route('asignaturas.store'), $payload);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors(['nombre_asignatura' => $message]);
        $this->assertDatabaseCount('asignatura', 0);
    }

    public static function invalidNames(): array
    {
        $required = 'El nombre de la asignatura es obligatorio.';
        $string = 'El nombre de la asignatura debe ser texto.';

        return [
            'missing' => [[], $required],
            'null' => [['nombre_asignatura' => null], $required],
            'empty' => [['nombre_asignatura' => ''], $required],
            'whitespace' => [['nombre_asignatura' => '   '], $required],
            'integer' => [['nombre_asignatura' => 123], $string],
            'boolean' => [['nombre_asignatura' => true], $string],
            'array' => [['nombre_asignatura' => ['Cálculo I']], $string],
            'too long' => [
                ['nombre_asignatura' => str_repeat('á', 151)],
                'El nombre de la asignatura no puede superar los 150 caracteres.',
            ],
        ];
    }

    public function test_accepts_a_name_with_150_characters(): void
    {
        $name = str_repeat('á', 150);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('asignaturas.store'), ['nombre_asignatura' => $name]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asignatura', ['nombre_asignatura' => $name]);
    }

    #[DataProvider('duplicateNames')]
    public function test_rejects_an_existing_name(string $name): void
    {
        Asignatura::create(['nombre_asignatura' => 'Cálculo I']);

        $response = $this->actingAs(User::factory()->create())
            ->from('/dashboard')->post(route('asignaturas.store'), [
                'nombre_asignatura' => $name,
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHasErrors([
            'nombre_asignatura' => 'Ya existe una asignatura con ese nombre',
        ]);
        $this->assertDatabaseCount('asignatura', 1);
    }

    public static function duplicateNames(): array
    {
        return [
            'exact' => ['Cálculo I'],
            'surrounding whitespace' => ['  Cálculo I  '],
        ];
    }

    public function test_guest_is_redirected_to_login_without_creating_a_subject(): void
    {
        $response = $this->post(route('asignaturas.store'), [
            'nombre_asignatura' => 'Cálculo I',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('asignatura', 0);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $role = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $role->id_rol]);

        $response = $this->actingAs($user)->post(route('asignaturas.store'), [
            'nombre_asignatura' => 'Cálculo I',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('asignatura', 0);
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
