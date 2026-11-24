<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportesPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_assigns_and_revokes_a_reporting_permission(): void
    {
        $admin = User::factory()->create();
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia de prueba']);
        $restringida = Asignatura::create(['nombre_asignatura' => 'Materia restringida']);
        $this->actingAs($admin)->post('/roles', [
            'nombre_rol' => '  Auxiliar  de reportes ',
            'permisos' => ['reportes.examenes'],
            'asignaturas_reportes' => [$asignatura->id_asignatura],
        ])->assertSessionHasNoErrors();

        $rol = Rol::where('nombre_rol', 'auxiliar de reportes')->firstOrFail();
        $this->post('/usuarios', [
            'id_rol' => $rol->id_rol,
            'nombres' => 'Auxiliar',
            'apellidos' => 'Prueba',
            'documento_identidad' => '5623412',
            'codigo_universitario' => '201809375',
            'password' => 'Segura.2026-Qr!',
            'password_confirmation' => 'Segura.2026-Qr!',
        ])->assertSessionHasNoErrors();
        $usuario = User::where('id_rol', $rol->id_rol)->firstOrFail();
        $usuario->update(['debe_cambiar_password' => false]);

        $periodo = $this->crearPeriodo();
        foreach ([$asignatura, $restringida] as $materia) {
            Examen::create([
                'id_asignatura' => $materia->id_asignatura,
                'id_periodo' => $periodo->id_periodo,
                'fecha' => now()->subDay()->toDateString(),
                'hora_inicio' => '10:00',
                'duracion_minutos' => 60,
            ]);
        }
        $filtros = ['formato' => 'excel', 'id_periodo' => $periodo->id_periodo];

        $this->actingAs($usuario)->get('/dashboard')->assertRedirect(route('reportes.index'));
        $this->get('/reportes')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Reportes/Index')
            ->where('reportes', ['reportes.examenes' => 'Exportar exámenes'])
            ->has('asignaturas', 1)
        );
        $this->get(route('reportes.examenes', $filtros))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="examenes.xls"')
            ->assertSee('Materia de prueba')->assertDontSee('Materia restringida');
        $this->get(route('reportes.examenes', $filtros + ['id_asignatura' => $restringida->id_asignatura]))
            ->assertForbidden();
        $this->get(route('reportes.incidencias', $filtros))->assertForbidden();
        $this->get('/examenes')->assertForbidden();
        $this->post('/examenes')->assertForbidden();
        $this->post('/roles')->assertForbidden();

        $this->actingAs($admin)->patch(route('roles.update', $rol), [
            'nombre_rol' => $rol->nombre_rol, 'permisos' => [],
        ])->assertSessionHasNoErrors();
        $this->actingAs($usuario->fresh())->get(route('reportes.examenes', $filtros))->assertForbidden();
    }

    public function test_admin_rejects_reserved_names_and_permissions_outside_the_catalog(): void
    {
        $this->actingAs(User::factory()->create())->post('/roles', [
            'nombre_rol' => ' Administrador ',
            'permisos' => ['usuarios.store'],
        ])->assertSessionHasErrors(['nombre_rol', 'permisos.0']);
    }
}
