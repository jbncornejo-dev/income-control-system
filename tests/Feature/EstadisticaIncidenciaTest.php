<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Incidencia;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EstadisticaIncidenciaTest extends TestCase
{
    use RefreshDatabase;

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

    private function examen(): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);

        $periodo = Periodo::firstOrCreate(
            ['gestion' => (int) now()->format('Y'), 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => now()->startOfYear()->toDateString(), 'fecha_fin' => now()->endOfYear()->toDateString()]
        );

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $periodo->id_periodo,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 120,
        ]);
    }

    private function incidencia(Examen $examen, string $tipo, ?string $cuando = null): Incidencia
    {
        return Incidencia::create([
            'id_examen' => $examen->id_examen,
            'id_usuario' => $this->usuarioConRol('personal de control de ingreso')->id,
            'tipo_incidencia' => $tipo,
            'descripcion_motivo' => 'Motivo de prueba',
            'fecha_hora' => $cuando ? $cuando.' 10:00:00' : now(),
        ]);
    }

    public function test_devuelve_el_total_y_el_desglose_por_tipo(): void
    {
        $examen = $this->examen();
        $this->incidencia($examen, 'Intento indebido');
        $this->incidencia($examen, 'Intento indebido');
        $this->incidencia($examen, 'Retraso');

        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas'))
            ->assertOk()
            ->assertJsonPath('resumen.total', 3)
            ->assertJsonPath('resumen.tipo_mas_frecuente', 'Intento indebido')
            ->assertJsonPath('porTipo.0.tipo', 'Intento indebido')
            ->assertJsonPath('porTipo.0.total', 2)
            ->assertJsonPath('porTipo.1.tipo', 'Retraso');
    }

    public function test_filtra_por_examen(): void
    {
        $uno = $this->examen();
        $otro = $this->examen();
        $this->incidencia($uno, 'Retraso');
        $this->incidencia($otro, 'Retraso');
        $this->incidencia($otro, 'Suplantacion');

        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas', ['id_examen' => $otro->id_examen]))
            ->assertOk()
            ->assertJsonPath('resumen.total', 2)
            ->assertJsonPath('resumen.examenes_afectados', 1);
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $examen = $this->examen();
        $this->incidencia($examen, 'Retraso', now()->subDays(10)->toDateString());
        $this->incidencia($examen, 'Retraso', now()->subDay()->toDateString());

        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas', ['desde' => now()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertJsonPath('resumen.total', 1);
    }

    public function test_agrupa_las_incidencias_por_dia(): void
    {
        $examen = $this->examen();
        $this->incidencia($examen, 'Retraso', now()->subDays(2)->toDateString());
        $this->incidencia($examen, 'Retraso', now()->subDay()->toDateString());
        $this->incidencia($examen, 'Suplantacion', now()->subDay()->toDateString());

        $respuesta = $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas'))
            ->assertOk()
            ->assertJsonPath('resumen.dias_con_registro', 2)
            ->assertJsonPath('porDia.0.total', 1)
            ->assertJsonPath('porDia.1.total', 2);

        $this->assertSame(now()->subDays(2)->toDateString(), $respuesta->json('porDia.0.dia'));
    }

    public function test_devuelve_ceros_cuando_no_hay_incidencias(): void
    {
        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas'))
            ->assertOk()
            ->assertJsonPath('resumen.total', 0)
            ->assertJsonPath('resumen.tipo_mas_frecuente', null)
            ->assertJsonCount(0, 'porTipo');
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson(route('incidencias.estadisticas', [
                'desde' => now()->toDateString(),
                'hasta' => now()->subWeek()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
    }

    public function test_el_estudiante_no_accede_a_las_estadisticas(): void
    {
        $this->actingAs($this->usuarioConRol('estudiante'))
            ->get(route('incidencias.estadisticas'))
            ->assertForbidden();
    }
}
