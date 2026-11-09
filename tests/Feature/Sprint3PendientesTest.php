<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint3PendientesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'administrador']);
        return User::factory()->create(['id_rol' => $rol->id_rol, 'debe_cambiar_password' => false]);
    }

    public function test_auditoria_registra_operaciones_y_filtra_por_tabla(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/ambientes', [
            'nombre_ambiente' => 'Auditorio Central',
            'capacidad' => 120,
        ])->assertRedirect();
        $ambiente = Ambiente::where('nombre_ambiente', 'Auditorio Central')->firstOrFail();

        $this->assertDatabaseHas('auditoria_log', [
            'id_usuario' => $admin->id,
            'tabla_afectada' => 'ambiente',
            'id_registro_afectado' => $ambiente->id_ambiente,
            'accion' => 'CREATE',
        ]);
        $this->actingAs($admin)->getJson('/auditoria?tabla_afectada=ambiente')
            ->assertOk()->assertJsonCount(1, 'logs.data');
    }

    public function test_registro_ingreso_rechaza_examen_fuera_de_ventana(): void
    {
        $admin = $this->admin();
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Física I']);
        $periodo = Periodo::create([
            'gestion' => 2026, 'tipo' => 'semestre', 'numero' => 2,
            'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-20',
        ]);
        $inicio = now()->addHours(3);
        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura, 'id_periodo' => $periodo->id_periodo,
            'fecha' => $inicio->toDateString(), 'hora_inicio' => $inicio->format('H:i'), 'duracion_minutos' => 60,
        ]);
        $estudiante = Estudiante::create([
            'codigo_universitario' => '202012345', 'documento_identidad' => '8765432',
            'nombres' => 'Carlos', 'apellidos' => 'Pérez',
        ]);
        Habilitacion::create([
            'id_examen' => $examen->id_examen, 'id_estudiante' => $estudiante->id_estudiante,
            'estado_habilitado' => true,
        ]);

        $this->actingAs($admin)->postJson('/registro-ingreso/validar', [
            'id_examen' => $examen->id_examen, 'dato_estudiante' => '202012345',
        ])->assertStatus(422);
    }

    public function test_exporta_asistencia_en_pdf_y_excel(): void
    {
        $admin = $this->admin();
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo II']);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 690A', 'capacidad' => 50]);
        $periodo = Periodo::create([
            'gestion' => 2026, 'tipo' => 'semestre', 'numero' => 2,
            'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-20',
        ]);
        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura, 'id_periodo' => $periodo->id_periodo,
            'fecha' => now()->toDateString(), 'hora_inicio' => now()->format('H:i'), 'duracion_minutos' => 90,
        ]);
        ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);

        $this->actingAs($admin)->get("/panel-asistencia/{$examen->id_examen}/exportar?formato=pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get("/panel-asistencia/{$examen->id_examen}/exportar?formato=excel")
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
    }
}
