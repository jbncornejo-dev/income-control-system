<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected $docenteUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $adminRol = Rol::create(['nombre_rol' => 'administrador']);
        $docenteRol = Rol::create(['nombre_rol' => 'docente']);

        $this->adminUser = User::factory()->create(['id_rol' => $adminRol->id_rol]);
        $this->docenteUser = User::factory()->create(['id_rol' => $docenteRol->id_rol]);
    }

    /** @test */
    public function it_returns_zero_when_database_is_empty()
    {
        // Caso 5: Base de datos vacía
        // Note: The database has 2 users (admin and docente) from setUp
        User::query()->delete();
        $adminUserAlone = User::factory()->create(['id_rol' => Rol::where('nombre_rol', 'administrador')->first()->id_rol]);

        $this->actingAs($adminUserAlone)
            ->get(route('admin.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.estudiantes', 0)
                ->where('stats.examenes', 0)
                ->where('stats.ambientes', 0)
                ->where('stats.usuarios', 1) // Only the admin user
            );
    }

    /** @test */
    public function it_returns_correct_counts_for_admin_metrics()
    {
        // Casos 1, 2, 3 y 4: Conteo de estudiantes, exámenes, ambientes y usuarios
        for ($i = 0; $i < 3; $i++) {
            Estudiante::create([
                'nombres' => "Estudiante $i",
                'apellidos' => 'Prueba',
                'codigo_universitario' => "1000$i",
                'documento_identidad' => "7000$i",
            ]);
        }

        // Examen depends on Asignatura, let's just insert bypassing validation or create asignatura
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Test']);
        $periodo = $this->crearPeriodo('2026', 1);
        for ($i = 0; $i < 2; $i++) {
            Examen::create([
                'id_asignatura' => $asignatura->id_asignatura,
                'id_periodo' => $periodo->id_periodo,
                'fecha' => '2026-01-01',
                'hora_inicio' => '10:00:00',
                'duracion_minutos' => 90,
            ]);
        }

        for ($i = 0; $i < 4; $i++) {
            Ambiente::create([
                'nombre_ambiente' => "Ambiente $i",
                'capacidad' => 30,
            ]);
        }

        User::factory()->count(3)->create(['id_rol' => Rol::where('nombre_rol', 'docente')->first()->id_rol]);

        // Total users = 2 from setUp + 3 new = 5

        $this->actingAs($this->adminUser)
            ->get(route('admin.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.estudiantes', 3)
                ->where('stats.examenes', 2)
                ->where('stats.ambientes', 4)
                ->where('stats.usuarios', 5)
            );
    }

    /** @test */
    public function it_reflects_updated_data()
    {
        // Caso 6: Datos actualizados
        $this->actingAs($this->adminUser)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.estudiantes', 0)
            );

        for ($i = 0; $i < 5; $i++) {
            Estudiante::create([
                'nombres' => "Estudiante $i",
                'apellidos' => 'Prueba',
                'codigo_universitario' => "2000$i",
                'documento_identidad' => "8000$i",
            ]);
        }

        $this->actingAs($this->adminUser)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.estudiantes', 5)
            );
    }

    /** @test */
    public function it_restricts_access_to_authorized_users()
    {
        // Caso 7: Autorización (solo admin puede acceder)
        $this->actingAs($this->docenteUser)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    /** @test */
    public function it_redirects_unauthenticated_users()
    {
        // Caso 8: Usuario no autenticado
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }
}
