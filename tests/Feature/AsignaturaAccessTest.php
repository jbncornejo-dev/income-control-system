<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AsignaturaAccessTest extends TestCase
{
    use RefreshDatabase;

    private Asignatura $asignatura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
    }

    #[DataProvider('operations')]
    public function test_json_guests_receive_401(string $method, string $routeName, bool $requiresId): void
    {
        $this->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_asignatura' => 'Física',
        ])->assertUnauthorized();

        $this->assertSubjectUnchanged();
    }

    #[DataProvider('operations')]
    public function test_unrecognized_role_receives_403(string $method, string $routeName, bool $requiresId): void
    {
        $rol = Rol::create(['nombre_rol' => 'administrador_auxiliar']);
        $user = User::factory()->create(['id_rol' => $rol->id_rol]);

        $this->actingAs($user)->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_asignatura' => 'Física',
        ])->assertForbidden();

        $this->assertSubjectUnchanged();
    }

    #[DataProvider('operations')]
    public function test_missing_role_relation_receives_403(string $method, string $routeName, bool $requiresId): void
    {
        $user = User::factory()->create();
        // La BD exige id_rol válido; simular la relación ausente en memoria
        // permite verificar el rechazo defensivo sin alterar sus restricciones.
        $user->setRelation('rol', null);

        $this->actingAs($user)->json($method, $this->operationUrl($routeName, $requiresId), [
            'nombre_asignatura' => 'Física',
        ])->assertForbidden();

        $this->assertSubjectUnchanged();
    }

    private function operationUrl(string $routeName, bool $requiresId): string
    {
        return route($routeName, $requiresId ? $this->asignatura : []);
    }

    private function assertSubjectUnchanged(): void
    {
        $this->assertDatabaseCount('asignatura', 1);
        $this->assertDatabaseHas('asignatura', [
            'id_asignatura' => $this->asignatura->id_asignatura,
            'nombre_asignatura' => 'Cálculo I',
        ]);
    }

    public static function operations(): array
    {
        return [
            'list and search' => ['GET', 'asignaturas.index', false],
            'store' => ['POST', 'asignaturas.store', false],
            'update' => ['PATCH', 'asignaturas.update', true],
            'destroy' => ['DELETE', 'asignaturas.destroy', true],
        ];
    }
}
