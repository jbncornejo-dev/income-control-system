<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Asignatura::insert([
            ['id_asignatura' => 1, 'nombre_asignatura' => 'Cálculo I'],
            ['id_asignatura' => 12, 'nombre_asignatura' => 'Introducción al CÁLCULO'],
            ['id_asignatura' => 21, 'nombre_asignatura' => 'Física'],
        ]);
    }

    // Al integrar Inertia, adaptar las aserciones JSON a assertInertia
    // conservando las comprobaciones de la prop 'asignaturas'.
    #[DataProvider('searches')]
    public function test_filters_subjects(array $filters, array $expectedIds): void
    {
        $response = $this->getJson(route('asignaturas.index', $filters));

        $response->assertOk()->assertJsonPath('asignaturas.total', count($expectedIds));
        $this->assertSame($expectedIds, array_column($response->json('asignaturas.data'), 'id_asignatura'));
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
            'accent sensitive' => [['nombre_asignatura' => 'calculo'], []],
            'empty filters' => [['id_asignatura' => '', 'nombre_asignatura' => ''], [1, 12, 21]],
            'whitespace filters' => [['id_asignatura' => '  ', 'nombre_asignatura' => '  '], [1, 12, 21]],
            'no filters' => [[], [1, 12, 21]],
        ];
    }

    #[DataProvider('literalNames')]
    public function test_searches_special_characters_literally(string $name, string $filter): void
    {
        Asignatura::insert(['id_asignatura' => 40, 'nombre_asignatura' => $name]);

        $this->getJson(route('asignaturas.index', ['nombre_asignatura' => $filter]))
            ->assertOk()
            ->assertJsonPath('asignaturas.total', 1)
            ->assertJsonPath('asignaturas.data.0.id_asignatura', 40);
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

        $first = $this->getJson(route('asignaturas.index', ['nombre_asignatura' => 'álgebra']));
        $first->assertOk()->assertJsonCount(15, 'asignaturas.data')->assertJsonPath('asignaturas.total', 17);
        parse_str(parse_url($first->json('asignaturas.next_page_url'), PHP_URL_QUERY), $query);
        $this->assertSame('álgebra', $query['nombre_asignatura']);
        $this->assertSame('2', $query['page']);

        $second = $this->getJson($first->json('asignaturas.next_page_url'));
        $second->assertOk()->assertJsonCount(2, 'asignaturas.data')->assertJsonPath('asignaturas.total', 17);
        $this->assertSame($ids, array_column([
            ...$first->json('asignaturas.data'),
            ...$second->json('asignaturas.data'),
        ], 'id_asignatura'));
    }
}
