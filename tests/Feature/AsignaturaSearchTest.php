<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El resolvedor Vue usa Pages con mayúscula; ajustar solo el entorno de pruebas.
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);

        $this->actingAs(User::factory()->create());
        Asignatura::insert([
            ['id_asignatura' => 1, 'nombre_asignatura' => 'Cálculo I'],
            ['id_asignatura' => 12, 'nombre_asignatura' => 'Introducción al CÁLCULO'],
            ['id_asignatura' => 21, 'nombre_asignatura' => 'Física'],
        ]);
    }

    #[DataProvider('searches')]
    public function test_filters_subjects(array $filters, array $expectedIds): void
    {
        $response = $this->get(route('asignaturas.index', $filters));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->where('asignaturas.total', count($expectedIds)));
        $this->assertSame($expectedIds, array_column($response->inertiaProps('asignaturas.data'), 'id_asignatura'));
    }

    public static function searches(): array
    {
        return [
            'exact ID' => [['id_asignatura' => 1], [1]],
            'missing ID' => [['id_asignatura' => 999], []],
            'partial case insensitive name' => [['nombre_asignatura' => 'cÁlCuLo'], [1, 12]],
            'trim name' => [['nombre_asignatura' => '  cálculo  '], [1, 12]],
            'combined matching filters' => [['id_asignatura' => 12, 'nombre_asignatura' => 'cálculo'], [12]],
            'combined conflicting filters' => [['id_asignatura' => 21, 'nombre_asignatura' => 'cálculo'], []],
            'no matches' => [['nombre_asignatura' => 'Química'], []],
            'without accent' => [['nombre_asignatura' => 'calculo'], [1, 12]],
            'empty filters' => [['id_asignatura' => '', 'nombre_asignatura' => ''], [1, 12, 21]],
            'whitespace filters' => [['id_asignatura' => '  ', 'nombre_asignatura' => '  '], [1, 12, 21]],
            'no filters' => [[], [1, 12, 21]],
        ];
    }

    public function test_accented_search_matches_name_without_accent(): void
    {
        Asignatura::insert(['id_asignatura' => 40, 'nombre_asignatura' => 'Algebra Lineal']);

        $this->get(route('asignaturas.index', ['nombre_asignatura' => 'álgebra']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')
                ->where('asignaturas.total', 1)
                ->where('asignaturas.data.0.id_asignatura', 40));
    }

    #[DataProvider('literalNames')]
    public function test_searches_special_characters_literally(string $name, string $filter): void
    {
        Asignatura::insert(['id_asignatura' => 40, 'nombre_asignatura' => $name]);

        $this->get(route('asignaturas.index', ['nombre_asignatura' => $filter]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->where('asignaturas.total', 1)
                ->where('asignaturas.data.0.id_asignatura', 40));
    }

    public static function literalNames(): array
    {
        return [
            'percent' => ['Avance 100%', '%'],
            'underscore' => ['Tema_A', '_'],
            'backslash' => ['Tema\\A', '\\'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_rejects_invalid_filters(array $filters, string $field): void
    {
        $this->getJson(route('asignaturas.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'zero ID' => [['id_asignatura' => 0], 'id_asignatura'],
            'negative ID' => [['id_asignatura' => -1], 'id_asignatura'],
            'decimal ID' => [['id_asignatura' => '1.5'], 'id_asignatura'],
            'text ID' => [['id_asignatura' => 'abc'], 'id_asignatura'],
            'array ID' => [['id_asignatura' => [1]], 'id_asignatura'],
            'array name' => [['nombre_asignatura' => ['Cálculo']], 'nombre_asignatura'],
            'long name' => [['nombre_asignatura' => str_repeat('á', 151)], 'nombre_asignatura'],
        ];
    }

    public function test_filtered_pagination_preserves_search_and_excludes_other_subjects(): void
    {
        $ids = [];
        for ($i = 30; $i < 47; $i++) {
            Asignatura::insert(['id_asignatura' => $i, 'nombre_asignatura' => "Álgebra {$i}"]);
            $ids[] = $i;
        }

        $first = $this->get(route('asignaturas.index', ['nombre_asignatura' => 'álgebra']));
        $first->assertOk()->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->has('asignaturas.data', 15)
            ->where('asignaturas.total', 17));
        parse_str(parse_url($first->inertiaProps('asignaturas.next_page_url'), PHP_URL_QUERY), $query);
        $this->assertSame('álgebra', $query['nombre_asignatura']);
        $this->assertSame('2', $query['page']);

        $second = $this->get($first->inertiaProps('asignaturas.next_page_url'));
        $second->assertOk()->assertInertia(fn (Assert $page) => $page->component('Asignaturas/Index')->has('asignaturas.data', 2)
            ->where('asignaturas.total', 17));
        $this->assertSame($ids, array_column([
            ...$first->inertiaProps('asignaturas.data'),
            ...$second->inertiaProps('asignaturas.data'),
        ], 'id_asignatura'));
    }
}
