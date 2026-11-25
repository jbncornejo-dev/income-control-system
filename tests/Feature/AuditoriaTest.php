<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use App\Observers\AuditoriaObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AuditoriaObserver::$habilitadoEnPruebas = true;
    }

    protected function tearDown(): void
    {
        AuditoriaObserver::$habilitadoEnPruebas = false;
        parent::tearDown();
    }

    private function usuarioConRol(string $rol): User
    {
        $registro = Rol::firstOrCreate(['nombre_rol' => $rol]);

        return User::create([
            'id_rol' => $registro->id_rol,
            'name' => 'Usuario '.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => Hash::make('pass'),
            'email_verified_at' => now(),
        ]);
    }

    private function administradorLimpio(): User
    {
        $usuario = $this->usuarioConRol('administrador');
        AuditoriaLog::query()->delete();

        return $usuario;
    }

    public function test_registra_el_alta_de_un_ambiente(): void
    {
        $admin = $this->administradorLimpio();

        $this->actingAs($admin);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula '.uniqid(), 'capacidad' => 40]);

        $this->assertDatabaseHas('auditoria_log', [
            'id_usuario' => $admin->id,
            'tabla_afectada' => $ambiente->getTable(),
            'id_registro_afectado' => $ambiente->getKey(),
            'accion' => 'CREATE',
        ]);
    }

    public function test_registra_la_modificacion_y_la_baja(): void
    {
        $admin = $this->administradorLimpio();

        $this->actingAs($admin);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula '.uniqid(), 'capacidad' => 40]);
        $ambiente->update(['capacidad' => 60]);
        $clave = $ambiente->getKey();
        $ambiente->delete();

        $this->assertDatabaseHas('auditoria_log', [
            'id_registro_afectado' => $clave,
            'accion' => 'UPDATE',
        ]);
        $this->assertDatabaseHas('auditoria_log', [
            'id_registro_afectado' => $clave,
            'accion' => 'DELETE',
        ]);
    }

    public function test_no_duplica_la_modificacion_de_un_examen(): void
    {
        $admin = $this->administradorLimpio();
        $this->actingAs($admin);

        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);
        $periodo = Periodo::firstOrCreate(
            ['gestion' => (int) now()->format('Y'), 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => now()->startOfYear()->toDateString(), 'fecha_fin' => now()->endOfYear()->toDateString()]
        );

        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $periodo->id_periodo,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 120,
        ]);
        $examen->update(['duracion_minutos' => 150]);

        $this->assertDatabaseHas('auditoria_log', [
            'tabla_afectada' => $examen->getTable(),
            'id_registro_afectado' => $examen->getKey(),
            'accion' => 'CREATE',
        ]);
        $this->assertDatabaseMissing('auditoria_log', [
            'tabla_afectada' => $examen->getTable(),
            'id_registro_afectado' => $examen->getKey(),
            'accion' => 'UPDATE',
        ]);
    }

    public function test_lista_las_operaciones_registradas(): void
    {
        $admin = $this->administradorLimpio();

        AuditoriaLog::create([
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'ambiente',
            'id_registro_afectado' => 7,
            'accion' => 'CREATE',
            'fecha_hora' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('auditoria.index'))
            ->assertOk()
            ->assertJsonPath('registros.total', 1)
            ->assertJsonPath('registros.data.0.tabla_afectada', 'ambiente')
            ->assertJsonPath('registros.data.0.accion', 'CREATE');
    }

    public function test_filtra_por_accion_y_por_usuario(): void
    {
        $admin = $this->administradorLimpio();
        $otro = $this->usuarioConRol('docente');
        AuditoriaLog::query()->delete();

        AuditoriaLog::create([
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'ambiente',
            'id_registro_afectado' => 1,
            'accion' => 'CREATE',
            'fecha_hora' => now(),
        ]);
        AuditoriaLog::create([
            'id_usuario' => $otro->id,
            'tabla_afectada' => 'examen',
            'id_registro_afectado' => 2,
            'accion' => 'DELETE',
            'fecha_hora' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('auditoria.index', ['accion' => 'DELETE']))
            ->assertOk()
            ->assertJsonPath('registros.total', 1)
            ->assertJsonPath('registros.data.0.tabla_afectada', 'examen');

        $this->actingAs($admin)
            ->getJson(route('auditoria.index', ['id_usuario' => $admin->id]))
            ->assertOk()
            ->assertJsonPath('registros.total', 1)
            ->assertJsonPath('registros.data.0.accion', 'CREATE');
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $admin = $this->administradorLimpio();

        AuditoriaLog::create([
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'ambiente',
            'id_registro_afectado' => 1,
            'accion' => 'CREATE',
            'fecha_hora' => now()->subDays(10),
        ]);
        AuditoriaLog::create([
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'ambiente',
            'id_registro_afectado' => 2,
            'accion' => 'UPDATE',
            'fecha_hora' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('auditoria.index', ['desde' => now()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertJsonPath('registros.total', 1)
            ->assertJsonPath('registros.data.0.accion', 'UPDATE');
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $admin = $this->administradorLimpio();

        $this->actingAs($admin)
            ->getJson(route('auditoria.index', [
                'desde' => now()->toDateString(),
                'hasta' => now()->subWeek()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
    }

    public function test_solo_el_administrador_consulta_la_auditoria(): void
    {
        foreach (['docente', 'personal de control de ingreso', 'estudiante'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->get(route('auditoria.index'))
                ->assertForbidden();
        }

        auth()->logout();

        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('auditoria.index'))
            ->assertOk();
    }
}
