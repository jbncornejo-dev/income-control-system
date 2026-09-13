<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbienteIndexTest extends TestCase
{
    use RefreshDatabase;

    // Al integrar Inertia, adaptar las aserciones JSON a la prop 'ambientes'.
    public function test_administrador_can_list_empty_catalog(): void
    {
        $this->actingAs(User::factory()->create())->get(route('ambientes.index'))
            ->assertOk()
            ->assertJsonPath('ambientes.data', [])
            ->assertJsonPath('ambientes.total', 0)
            ->assertJsonPath('ambientes.current_page', 1)
            ->assertJsonPath('ambientes.per_page', 15)
            ->assertJsonPath('ambientes.next_page_url', null);
    }

    public function test_lists_only_id_name_and_capacity_in_id_order(): void
    {
        Ambiente::insert([
            ['id_ambiente' => 30, 'nombre_ambiente' => 'Aula A', 'capacidad' => 50],
            ['id_ambiente' => 10, 'nombre_ambiente' => 'Laboratorio', 'capacidad' => 20],
            ['id_ambiente' => 20, 'nombre_ambiente' => 'Auditorio', 'capacidad' => 100],
        ]);

        $this->actingAs(User::factory()->create())->get(route('ambientes.index'))
            ->assertOk()->assertJsonPath('ambientes.data', [
                ['id_ambiente' => 10, 'nombre_ambiente' => 'Laboratorio', 'capacidad' => 20],
                ['id_ambiente' => 20, 'nombre_ambiente' => 'Auditorio', 'capacidad' => 100],
                ['id_ambiente' => 30, 'nombre_ambiente' => 'Aula A', 'capacidad' => 50],
            ]);
    }

    public function test_pagination_does_not_repeat_or_omit_rooms(): void
    {
        $ids = [];
        for ($i = 1; $i <= 17; $i++) {
            $ids[] = Ambiente::create(['nombre_ambiente' => "Aula {$i}", 'capacidad' => 40])->id_ambiente;
        }

        $this->actingAs(User::factory()->create());
        $first = $this->get(route('ambientes.index'));
        $first->assertOk()
            ->assertJsonCount(15, 'ambientes.data')
            ->assertJsonPath('ambientes.total', 17)
            ->assertJsonPath('ambientes.per_page', 15)
            ->assertJsonPath('ambientes.current_page', 1)
            ->assertJsonPath('ambientes.last_page', 2)
            ->assertJsonPath('ambientes.prev_page_url', null)
            ->assertJsonPath('ambientes.next_page_url', route('ambientes.index', ['page' => 2]));

        $second = $this->get($first->json('ambientes.next_page_url'));
        $second->assertOk()
            ->assertJsonCount(2, 'ambientes.data')
            ->assertJsonPath('ambientes.total', 17)
            ->assertJsonPath('ambientes.current_page', 2)
            ->assertJsonPath('ambientes.next_page_url', null)
            ->assertJsonPath('ambientes.prev_page_url', route('ambientes.index', ['page' => 1]));

        $this->assertSame($ids, array_column([
            ...$first->json('ambientes.data'),
            ...$second->json('ambientes.data'),
        ], 'id_ambiente'));
    }

    public function test_page_beyond_last_is_empty_and_preserves_total(): void
    {
        Ambiente::create(['nombre_ambiente' => 'Aula A', 'capacidad' => 40]);
        $this->actingAs(User::factory()->create())->get(route('ambientes.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('ambientes.data', [])
            ->assertJsonPath('ambientes.total', 1)
            ->assertJsonPath('ambientes.current_page', 2)
            ->assertJsonPath('ambientes.last_page', 1)
            ->assertJsonPath('ambientes.next_page_url', null);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ambientes.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $rol = Rol::create(['nombre_rol' => $roleName]);
        $this->actingAs(User::factory()->create(['id_rol' => $rol->id_rol]))
            ->get(route('ambientes.index'))->assertForbidden();
    }

    public static function nonAdministratorRoles(): array
    {
        return [['docente'], ['personal de control de ingreso'], ['estudiante']];
    }
}
