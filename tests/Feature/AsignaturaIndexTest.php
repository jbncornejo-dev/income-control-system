<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El resolvedor Vue usa Pages con mayúscula; ajustar solo el entorno de pruebas.
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
    }

    public function test_administrador_can_list_an_empty_catalog(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('asignaturas.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->where('asignaturas.data', [])
                ->where('asignaturas.total', 0)
                ->where('asignaturas.current_page', 1)
                ->where('asignaturas.per_page', 15)
                ->where('asignaturas.next_page_url', null)
                ->where('filtros.id_asignatura', null)
                ->where('filtros.nombre_asignatura', null));
    }

    public function test_lists_only_id_and_name_in_id_order(): void
    {
        Asignatura::insert([
            ['id_asignatura' => 30, 'nombre_asignatura' => 'Álgebra'],
            ['id_asignatura' => 10, 'nombre_asignatura' => 'Física'],
            ['id_asignatura' => 20, 'nombre_asignatura' => 'Cálculo I'],
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('asignaturas.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->where('asignaturas.data', [
            ['id_asignatura' => 10, 'nombre_asignatura' => 'Física'],
            ['id_asignatura' => 20, 'nombre_asignatura' => 'Cálculo I'],
            ['id_asignatura' => 30, 'nombre_asignatura' => 'Álgebra'],
        ]));
    }

    public function test_paginates_without_repeating_or_omitting_subjects(): void
    {
        $ids = [];
        for ($i = 1; $i <= 17; $i++) {
            $ids[] = Asignatura::create(['nombre_asignatura' => "Asignatura {$i}"])->id_asignatura;
        }

        $this->actingAs(User::factory()->create());
        $first = $this->get(route('asignaturas.index'));

        $first->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->has('asignaturas.data', 15)
                ->where('asignaturas.total', 17)
                ->where('asignaturas.last_page', 2)
                ->where('asignaturas.current_page', 1)
                ->where('asignaturas.prev_page_url', null)
                ->where('asignaturas.next_page_url', route('asignaturas.index', ['page' => 2])));

        $second = $this->get($first->inertiaProps('asignaturas.next_page_url'));
        $second->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->has('asignaturas.data', 2)
                ->where('asignaturas.total', 17)
                ->where('asignaturas.current_page', 2)
                ->where('asignaturas.next_page_url', null)
                ->where('asignaturas.prev_page_url', route('asignaturas.index', ['page' => 1])));

        $listedIds = array_column([
            ...$first->inertiaProps('asignaturas.data'),
            ...$second->inertiaProps('asignaturas.data'),
        ], 'id_asignatura');
        $this->assertSame($ids, $listedIds);
    }

    public function test_page_beyond_last_returns_empty_data_and_preserves_total(): void
    {
        Asignatura::create(['nombre_asignatura' => 'Cálculo I']);

        $this->actingAs(User::factory()->create())
            ->get(route('asignaturas.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->where('asignaturas.data', [])
                ->where('asignaturas.total', 1)
                ->where('asignaturas.current_page', 2)
                ->where('asignaturas.last_page', 1)
                ->where('asignaturas.next_page_url', null));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('asignaturas.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_other_roles_receive_403(string $roleName): void
    {
        $role = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $role->id_rol]);

        $this->actingAs($user)->get(route('asignaturas.index'))->assertForbidden();
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
