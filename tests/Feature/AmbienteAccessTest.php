<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmbienteAccessTest extends TestCase
{
    use RefreshDatabase;

    private Ambiente $ambiente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
    }

    #[DataProvider('operations')]
    public function test_json_guests_receive_401(string $method, string $routeName, bool $requiresId): void
    {
        $this->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_ambiente' => 'Laboratorio',
            'capacidad' => 80,
        ])->assertUnauthorized();

        $this->assertRoomUnchanged();
    }

    #[DataProvider('operations')]
    public function test_unrecognized_role_receives_403(string $method, string $routeName, bool $requiresId): void
    {
        $rol = Rol::create(['nombre_rol' => 'administrador_auxiliar']);
        $user = User::factory()->create(['id_rol' => $rol->id_rol]);

        $this->actingAs($user)->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_ambiente' => 'Laboratorio',
            'capacidad' => 80,
        ])->assertForbidden();

        $this->assertRoomUnchanged();
    }

    #[DataProvider('operations')]
    public function test_missing_role_relation_receives_403(string $method, string $routeName, bool $requiresId): void
    {
        $user = User::factory()->create();
        // La BD exige id_rol válido; simular la relación ausente en memoria
        // permite verificar el rechazo defensivo sin alterar sus restricciones.
        $user->setRelation('rol', null);

        $this->actingAs($user)->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_ambiente' => 'Laboratorio',
            'capacidad' => 80,
        ])->assertForbidden();

        $this->assertRoomUnchanged();
    }

    private function operationUrl(string $routeName, bool $requiresId): string
    {
        return route($routeName, $requiresId ? $this->ambiente : []);
    }

    private function assertRoomUnchanged(): void
    {
        $this->assertDatabaseCount('ambiente', 1);
        $this->assertDatabaseHas('ambiente', [
            'id_ambiente' => $this->ambiente->id_ambiente,
            'nombre_ambiente' => 'Aula 101',
            'capacidad' => 40,
        ]);
    }

    public static function operations(): array
    {
        return [
            'list and search' => ['GET', 'ambientes.index', false],
            'store' => ['POST', 'ambientes.store', false],
            'update' => ['PATCH', 'ambientes.update', true],
            'destroy' => ['DELETE', 'ambientes.destroy', true],
        ];
    }
}
