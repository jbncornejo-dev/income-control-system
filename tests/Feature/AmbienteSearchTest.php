<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbienteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Ambiente::insert([
            ['id_ambiente' => 10, 'nombre_ambiente' => 'Aula Norte', 'capacidad' => 40],
            ['id_ambiente' => 20, 'nombre_ambiente' => 'Gran AULA Sur', 'capacidad' => 80],
            ['id_ambiente' => 30, 'nombre_ambiente' => 'Laboratorio de Física', 'capacidad' => 20],
        ]);
    }

    // Al integrar Inertia, adaptar estas aserciones a la prop paginada 'ambientes'.
    #[DataProvider('searches')]
    public function test_searches_room_names(array $filters, array $expectedIds): void
    {
        $response = $this->getJson(route('ambientes.index', $filters));
        $response->assertOk()->assertJsonPath('ambientes.total', count($expectedIds));
        $this->assertSame($expectedIds, array_column($response->json('ambientes.data'), 'id_ambiente'));
    }

    public static function searches(): array
    {
        return [
            'partial case insensitive' => [['nombre_ambiente' => 'aUlA'], [10, 20]],
            'middle of name' => [['nombre_ambiente' => 'torio de'], [30]],
            'trim whitespace' => [['nombre_ambiente' => '  aula  '], [10, 20]],
            'no matches' => [['nombre_ambiente' => 'Biblioteca'], []],
            'accent sensitive' => [['nombre_ambiente' => 'Fisica'], []],
            'accent and case' => [['nombre_ambiente' => 'FÍSICA'], [30]],
            'exact capacity' => [['nombre_ambiente' => '80'], [20]],
            'capacity is not partial' => [['nombre_ambiente' => '8'], []],
            'numeric name and capacity' => [['nombre_ambiente' => '20'], [30]],
            'zero only searches names' => [['nombre_ambiente' => '0'], []],
            'out of range number only searches names' => [['nombre_ambiente' => '2147483648'], []],
            'empty' => [['nombre_ambiente' => ''], [10, 20, 30]],
            'whitespace' => [['nombre_ambiente' => '   '], [10, 20, 30]],
            'omitted' => [[], [10, 20, 30]],
        ];
    }

    public function test_numeric_search_matches_name_or_capacity_without_duplicates(): void
    {
        Ambiente::insert([
            ['id_ambiente' => 40, 'nombre_ambiente' => 'Aula 80', 'capacidad' => 80],
            ['id_ambiente' => 50, 'nombre_ambiente' => 'Sala 80', 'capacidad' => 40],
        ]);

        $response = $this->getJson(route('ambientes.index', ['nombre_ambiente' => '80']));

        $response->assertOk()->assertJsonPath('ambientes.total', 3);
        $this->assertSame([20, 40, 50], array_column($response->json('ambientes.data'), 'id_ambiente'));
    }

    #[DataProvider('literalNames')]
    public function test_special_characters_are_literal(string $name, string $filter): void
    {
        Ambiente::insert(['id_ambiente' => 40, 'nombre_ambiente' => $name, 'capacidad' => 40]);
        $this->getJson(route('ambientes.index', ['nombre_ambiente' => $filter]))
            ->assertOk()->assertJsonPath('ambientes.total', 1)
            ->assertJsonPath('ambientes.data.0.id_ambiente', 40);
    }

    public static function literalNames(): array
    {
        return [
            'percent' => ['Sala 100%', '%'],
            'underscore' => ['Sala_A', '_'],
            'backslash' => ['Sala\\A', '\\'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_filters_are_rejected(array $filters): void
    {
        $this->getJson(route('ambientes.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors('nombre_ambiente');
    }

    public static function invalidNames(): array
    {
        return [
            'array' => [['nombre_ambiente' => ['Aula']]],
            'too long' => [['nombre_ambiente' => str_repeat('á', 101)]],
        ];
    }

    public function test_accepts_a_100_character_filter(): void
    {
        $name = str_repeat('á', 100);
        Ambiente::insert(['id_ambiente' => 40, 'nombre_ambiente' => $name, 'capacidad' => 40]);
        $this->getJson(route('ambientes.index', ['nombre_ambiente' => $name]))
            ->assertOk()->assertJsonPath('ambientes.total', 1)
            ->assertJsonPath('ambientes.data.0.nombre_ambiente', $name);
    }

    public function test_pagination_preserves_filter_and_excludes_nonmatching_rooms(): void
    {
        $ids = range(40, 56);
        foreach ($ids as $id) {
            Ambiente::insert(['id_ambiente' => $id, 'nombre_ambiente' => "Sala {$id}", 'capacidad' => 40]);
        }
        $first = $this->getJson(route('ambientes.index', ['nombre_ambiente' => 'sala']));
        $first->assertOk()->assertJsonCount(15, 'ambientes.data')->assertJsonPath('ambientes.total', 17);
        parse_str(parse_url($first->json('ambientes.next_page_url'), PHP_URL_QUERY), $query);
        $this->assertSame('sala', $query['nombre_ambiente']);
        $this->assertSame('2', $query['page']);

        $second = $this->getJson($first->json('ambientes.next_page_url'));
        $second->assertOk()->assertJsonCount(2, 'ambientes.data')
            ->assertJsonPath('ambientes.total', 17)->assertJsonPath('ambientes.next_page_url', null);
        $this->assertSame($ids, array_column([
            ...$first->json('ambientes.data'), ...$second->json('ambientes.data'),
        ], 'id_ambiente'));
    }
}
