<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstudianteIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
    }

    private function crearEstudiante(array $overrides = []): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            ...$overrides,
        ]);
    }

    private function shape(Estudiante $estudiante): array
    {
        return [
            'id' => $estudiante->id_estudiante,
            'ci' => $estudiante->documento_identidad,
            'name' => trim($estudiante->nombres.' '.$estudiante->apellidos),
            'career' => $estudiante->codigo_universitario,
            'status' => 'active',
            'statusText' => 'Habilitada',
            'nombres' => $estudiante->nombres,
            'apellidos' => $estudiante->apellidos,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'documento_identidad' => $estudiante->documento_identidad,
            'codigo_qr' => $estudiante->codigo_qr,
        ];
    }

    public function test_administrador_can_list_an_empty_catalog(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('estudiantes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.data', [])
                ->where('estudiantes.total', 0)
                ->where('estudiantes.current_page', 1)
                ->where('estudiantes.per_page', 15)
                ->where('estudiantes.next_page_url', null)
                ->where('estudiantes.prev_page_url', null)
                ->where('filtros.search', null));
    }

    public function test_lists_mapped_students_ordered_by_apellidos(): void
    {
        $zamora = $this->crearEstudiante([
            'codigo_universitario' => '2019-00001',
            'documento_identidad' => '2222222',
            'nombres' => 'Luis',
            'apellidos' => 'Zamora',
        ]);
        $perez = $this->crearEstudiante([
            'codigo_universitario' => '2019-00002',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-2',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('estudiantes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.data.0', $this->shape($perez))
                ->where('estudiantes.data.1', $this->shape($zamora)));
    }

    public function test_paginates_without_repeating_or_omitting_students(): void
    {
        $ids = [];
        for ($i = 1; $i <= 17; $i++) {
            $ids[] = $this->crearEstudiante([
                'codigo_universitario' => "2020-{$i}",
                'documento_identidad' => (string) (1000 + $i),
                'nombres' => "Nombre {$i}",
                'apellidos' => 'Apellido '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ])->id_estudiante;
        }

        $this->actingAs(User::factory()->create());
        $first = $this->get(route('estudiantes.index'));

        $first->assertOk()->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
            ->has('estudiantes.data', 15)
            ->where('estudiantes.total', 17)
            ->where('estudiantes.last_page', 2)
            ->where('estudiantes.current_page', 1)
            ->where('estudiantes.prev_page_url', null)
            ->where('estudiantes.next_page_url', route('estudiantes.index', ['page' => 2])));

        $second = $this->get($first->inertiaProps('estudiantes.next_page_url'));

        $second->assertOk()->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
            ->has('estudiantes.data', 2)
            ->where('estudiantes.total', 17)
            ->where('estudiantes.current_page', 2)
            ->where('estudiantes.next_page_url', null)
            ->where('estudiantes.prev_page_url', route('estudiantes.index', ['page' => 1])));

        $listedIds = array_column([
            ...$first->inertiaProps('estudiantes.data'),
            ...$second->inertiaProps('estudiantes.data'),
        ], 'id');
        $this->assertSame($ids, $listedIds);
    }

    public function test_search_is_case_insensitive_across_all_fields(): void
    {
        $this->crearEstudiante([
            'codigo_universitario' => '2020-AAAA',
            'documento_identidad' => '1111111',
            'nombres' => 'María',
            'apellidos' => 'Gómez',
        ]);
        $this->crearEstudiante([
            'codigo_universitario' => '2020-BBBB',
            'documento_identidad' => '9999999',
            'nombres' => 'Pedro',
            'apellidos' => 'Suarez',
        ]);

        $casos = [
            ['aaaa', 'codigo_universitario', '2020-AAAA'],
            ['999999', 'documento_identidad', '9999999'],
            ['maría', 'nombres', 'María'],
            ['SUAREZ', 'apellidos', 'Suarez'],
        ];

        $this->actingAs(User::factory()->create());

        foreach ($casos as [$termino, $campo, $esperado]) {
            $this->get(route('estudiantes.index', ['search' => $termino]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                    ->where('estudiantes.total', 1)
                    ->where("estudiantes.data.0.{$campo}", $esperado)
                    ->where('filtros.search', $termino));
        }
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->crearEstudiante([
            'codigo_universitario' => '100%UNICO',
            'documento_identidad' => '7777777',
            'nombres' => 'Ruth',
            'apellidos' => 'Condori',
        ]);
        $this->crearEstudiante([
            'codigo_universitario' => '200%UIT',
            'documento_identidad' => '5555555',
            'nombres' => 'Pablo',
            'apellidos' => 'Mamani',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('estudiantes.index', ['search' => '%UN']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.total', 1)
                ->where('estudiantes.data.0.codigo_universitario', '100%UNICO'));
    }

    public function test_search_reports_metadata_and_persists_terms_in_pagination_links(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->crearEstudiante([
                'codigo_universitario' => "2020-{$i}",
                'documento_identidad' => (string) (1000 + $i),
                'nombres' => 'Estudiante',
                'apellidos' => 'Apellido '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }
        $this->crearEstudiante([
            'codigo_universitario' => '2021-OTRO',
            'documento_identidad' => '8888888',
            'nombres' => 'Fuera',
            'apellidos' => 'Del Filtro',
        ]);

        $this->actingAs(User::factory()->create());

        $this->get(route('estudiantes.index', ['search' => 'del filtro']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.total', 1)
                ->where('estudiantes.current_page', 1)
                ->where('estudiantes.last_page', 1)
                ->where('estudiantes.data.0.apellidos', 'Del Filtro'));

        $first = $this->get(route('estudiantes.index', ['search' => 'apellido']));
        $first->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.total', 16)
                ->where('estudiantes.last_page', 2)
                ->where('estudiantes.current_page', 1)
                ->where('filtros.search', 'apellido'));

        $second = $this->get($first->inertiaProps('estudiantes.next_page_url'));
        $second->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.total', 16)
                ->where('estudiantes.current_page', 2)
                ->has('estudiantes.data', 1)
                ->where('filtros.search', 'apellido'));
    }

    public function test_page_beyond_last_returns_empty_data_and_preserves_total(): void
    {
        $this->crearEstudiante();

        $this->actingAs(User::factory()->create())
            ->get(route('estudiantes.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Estudiantes/Index')
                ->where('estudiantes.data', [])
                ->where('estudiantes.total', 1)
                ->where('estudiantes.current_page', 2)
                ->where('estudiantes.last_page', 1)
                ->where('estudiantes.next_page_url', null));
    }

    public function test_invalid_search_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('estudiantes.index'))
            ->get(route('estudiantes.index', ['search' => ['a', 'b']]))
            ->assertSessionHasErrors('search');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('estudiantes.index'))->assertRedirect(route('login'));
    }

    #[DataProvider('authorizedRoles')]
    public function test_authorized_roles_can_list_students(string $roleName): void
    {
        $role = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $role->id_rol]);

        $this->actingAs($user)->get(route('estudiantes.index'))->assertOk();
    }

    public static function authorizedRoles(): array
    {
        return [
            'administrador' => ['administrador'],
            'docente' => ['docente'],
            'control' => ['personal de control de ingreso'],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_roles_without_permission_receive_403(string $roleName): void
    {
        $role = Rol::create(['nombre_rol' => $roleName]);
        $user = User::factory()->create(['id_rol' => $role->id_rol]);

        $this->actingAs($user)->get(route('estudiantes.index'))->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [
            'estudiante' => ['estudiante'],
        ];
    }
}
