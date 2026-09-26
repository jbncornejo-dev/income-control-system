<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Periodo;
use App\Models\Incidencia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncidenciaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $rol): User
    {
        $registro = Rol::firstOrCreate(['nombre_rol' => $rol]);

        return User::create([
            'id_rol' => $registro->id_rol,
            'name' => 'Usuario '.$rol,
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => Hash::make('pass'),
            'email_verified_at' => now(),
        ]);
    }

    private function examen(): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo '.uniqid()]);

        $periodo = Periodo::firstOrCreate(
            ['gestion' => (int) now()->format('Y'), 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => now()->startOfYear()->toDateString(), 'fecha_fin' => now()->endOfYear()->toDateString()]
        );

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $periodo->id_periodo,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);
    }

    private function estudiante(): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => 'COD'.random_int(10000, 99999),
            'documento_identidad' => (string) random_int(1000000, 9999999),
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
        ]);
    }

    public function test_los_tres_roles_autorizados_acceden_al_listado(): void
    {
        foreach (['administrador', 'docente', 'personal de control de ingreso'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/incidencias')
                ->assertOk();
        }
    }

    public function test_el_estudiante_no_accede_al_listado(): void
    {
        $this->actingAs($this->usuarioConRol('estudiante'))
            ->getJson('/incidencias')
            ->assertForbidden();
    }

    public function test_el_invitado_es_redirigido_al_login(): void
    {
        $this->get('/incidencias')->assertRedirect('/login');
    }

    public function test_registra_una_incidencia_con_fecha_y_usuario_automaticos(): void
    {
        $usuario = $this->usuarioConRol('personal de control de ingreso');
        $examen = $this->examen();
        $estudiante = $this->estudiante();

        $this->actingAs($usuario)->post('/incidencias', [
            'id_examen' => $examen->id_examen,
            'id_estudiante' => $estudiante->id_estudiante,
            'tipo_incidencia' => 'Expulsión',
            'descripcion_motivo' => 'Intento de copia durante el examen',
        ])->assertSessionHasNoErrors();

        $incidencia = Incidencia::first();

        $this->assertNotNull($incidencia);
        $this->assertSame($usuario->id, $incidencia->id_usuario);
        $this->assertNotNull($incidencia->fecha_hora);
    }

    public function test_el_estudiante_es_opcional(): void
    {
        $examen = $this->examen();

        $this->actingAs($this->usuarioConRol('docente'))->post('/incidencias', [
            'id_examen' => $examen->id_examen,
            'tipo_incidencia' => 'Cambio de ambiente',
            'descripcion_motivo' => 'Se trasladó al grupo por falta de espacio',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Incidencia::first()->id_estudiante);
    }

    public function test_rechaza_campos_obligatorios_vacios(): void
    {
        $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/incidencias', [])
            ->assertSessionHasErrors(['id_examen', 'tipo_incidencia', 'descripcion_motivo']);

        $this->assertSame(0, Incidencia::count());
    }

    public function test_rechaza_un_tipo_fuera_del_catalogo(): void
    {
        $examen = $this->examen();

        $this->actingAs($this->usuarioConRol('administrador'))->post('/incidencias', [
            'id_examen' => $examen->id_examen,
            'tipo_incidencia' => 'Robo',
            'descripcion_motivo' => 'Motivo de prueba',
        ])->assertSessionHasErrors('tipo_incidencia');

        $this->assertSame(0, Incidencia::count());
    }

    public function test_no_existen_rutas_para_editar_ni_eliminar(): void
    {
        $usuario = $this->usuarioConRol('administrador');
        $examen = $this->examen();

        $incidencia = Incidencia::create([
            'id_examen' => $examen->id_examen,
            'id_usuario' => $usuario->id,
            'tipo_incidencia' => 'Otro',
            'descripcion_motivo' => 'Registro original',
            'fecha_hora' => now(),
        ]);

        $this->actingAs($usuario)
            ->putJson('/incidencias/'.$incidencia->id_incidencia, ['descripcion_motivo' => 'Editado'])
            ->assertNotFound();

        $this->actingAs($usuario)
            ->deleteJson('/incidencias/'.$incidencia->id_incidencia)
            ->assertNotFound();

        $this->assertSame('Registro original', $incidencia->fresh()->descripcion_motivo);
    }

    public function test_filtra_por_examen_y_por_tipo(): void
    {
        $usuario = $this->usuarioConRol('administrador');
        $primero = $this->examen();
        $segundo = $this->examen();

        Incidencia::create([
            'id_examen' => $primero->id_examen,
            'id_usuario' => $usuario->id,
            'tipo_incidencia' => 'Expulsión',
            'descripcion_motivo' => 'Primera',
            'fecha_hora' => now(),
        ]);

        Incidencia::create([
            'id_examen' => $segundo->id_examen,
            'id_usuario' => $usuario->id,
            'tipo_incidencia' => 'Otro',
            'descripcion_motivo' => 'Segunda',
            'fecha_hora' => now(),
        ]);

        $porExamen = $this->actingAs($usuario)
            ->getJson('/incidencias?id_examen='.$primero->id_examen)
            ->json('incidencias.data');

        $this->assertCount(1, $porExamen);
        $this->assertSame('Primera', $porExamen[0]['descripcion_motivo']);

        $porTipo = $this->actingAs($usuario)
            ->getJson('/incidencias?tipo_incidencia=Otro')
            ->json('incidencias.data');

        $this->assertCount(1, $porTipo);
        $this->assertSame('Segunda', $porTipo[0]['descripcion_motivo']);
    }
}
