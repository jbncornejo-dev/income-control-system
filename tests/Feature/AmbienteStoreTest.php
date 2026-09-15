<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbienteStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_registers_room_without_choosing_id(): void
    {
        $this->actingAs(User::factory()->create())->from('/dashboard')
            ->post(route('ambientes.store'), [
                'nombre_ambiente' => '  Aula 101  ', 'capacidad' => 40, 'id_ambiente' => 99999,
            ])->assertRedirect('/dashboard')->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Ambiente registrado correctamente.');

        $this->assertDatabaseHas('ambiente', ['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $this->assertDatabaseMissing('ambiente', ['id_ambiente' => 99999]);
        $this->assertDatabaseCount('ambiente', 1);
    }

    #[DataProvider('invalidData')]
    public function test_rejects_invalid_data(array $data, string $field): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('ambientes.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('ambiente', 0);
    }

    public static function invalidData(): array
    {
        $cases = [
            'missing name' => [['capacidad' => 40], 'nombre_ambiente'],
            'missing capacity' => [['nombre_ambiente' => 'Aula 101'], 'capacidad'],
        ];
        foreach (['null' => null, 'empty' => '', 'spaces' => '  ', 'number' => 123, 'array' => ['Aula'], 'long' => str_repeat('á', 101)] as $label => $name) {
            $cases['name '.$label] = [['nombre_ambiente' => $name, 'capacidad' => 40], 'nombre_ambiente'];
        }
        foreach (['null' => null, 'empty' => '', 'zero' => 0, 'negative' => -1, 'decimal' => 1.5, 'text' => 'muchos', 'array' => [40], 'overflow' => 2147483648] as $label => $capacity) {
            $cases['capacity '.$label] = [['nombre_ambiente' => 'Aula 101', 'capacidad' => $capacity], 'capacidad'];
        }

        return $cases;
    }

    #[DataProvider('validLimits')]
    public function test_accepts_valid_limits_and_integer_form_values(int|string $capacity): void
    {
        $name = str_repeat('á', 100);
        $this->actingAs(User::factory()->create())
            ->post(route('ambientes.store'), ['nombre_ambiente' => $name, 'capacidad' => $capacity])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('ambiente', ['nombre_ambiente' => $name, 'capacidad' => (int) $capacity]);
    }

    public static function validLimits(): array
    {
        return ['minimum' => [1], 'maximum' => [2147483647], 'HTML form' => ['40']];
    }

    public function test_duplicate_name_is_rejected_after_trimming(): void
    {
        Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $this->actingAs(User::factory()->create())->from('/dashboard')
            ->post(route('ambientes.store'), ['nombre_ambiente' => '  Aula 101  ', 'capacidad' => 80])
            ->assertRedirect('/dashboard')->assertSessionHasErrors([
                'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
            ]);
        $this->assertDatabaseCount('ambiente', 1);
        $this->assertDatabaseHas('ambiente', ['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }

    public function test_unique_constraint_handles_insert_between_validation_and_save(): void
    {
        $this->actingAs(User::factory()->create());
        $inserted = false;
        // Insertar después de la consulta de unicidad reproduce la ventana de carrera.
        DB::listen(function (QueryExecuted $query) use (&$inserted) {
            if (! $inserted && str_starts_with($query->sql, 'select count(*)') && str_contains($query->sql, 'from "ambiente"')) {
                $inserted = true;
                Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
            }
        });
        $this->from('/dashboard')->post(route('ambientes.store'), ['nombre_ambiente' => 'Aula 101', 'capacidad' => 80])
            ->assertRedirect('/dashboard')->assertSessionHasErrors([
                'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
            ])->assertSessionHasInput('nombre_ambiente', 'Aula 101');
        $this->assertTrue($inserted);
        $this->assertDatabaseCount('ambiente', 1);
        $this->assertDatabaseHas('ambiente', ['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('ambientes.store'), ['nombre_ambiente' => 'Aula 101', 'capacidad' => 40])
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('ambiente', 0);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $role): void
    {
        $rol = Rol::create(['nombre_rol' => $role]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->post(route('ambientes.store'), ['nombre_ambiente' => 'Aula 101', 'capacidad' => 40])
            ->assertForbidden();
        $this->assertDatabaseCount('ambiente', 0);
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }
}
