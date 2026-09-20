<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardDocenteTest extends TestCase
{
    use RefreshDatabase;

    protected $docenteUser;

    protected $docenteUser2;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $docenteRol = Rol::where('nombre_rol', 'docente')->first() ?? Rol::create(['nombre_rol' => 'docente']);
        $adminRol = Rol::where('nombre_rol', 'administrador')->first() ?? Rol::create(['nombre_rol' => 'administrador']);

        $this->docenteUser = User::factory()->create(['id_rol' => $docenteRol->id_rol]);
        $this->docenteUser2 = User::factory()->create(['id_rol' => $docenteRol->id_rol]);
        $this->adminUser = User::factory()->create(['id_rol' => $adminRol->id_rol]);
    }

    /** @test */
    public function it_returns_upcoming_exams_for_assigned_subjects()
    {
        // Caso 1 — Docente con próximos exámenes
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Matemáticas']);
        Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);

        $examenFuturo = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Docente/Dashboard')
                ->where('stats.examenes', 1)
                ->has('proximosExamenes', 1, fn (Assert $page) => $page
                    ->where('id_examen', $examenFuturo->id_examen)
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_only_returns_exams_for_the_authenticated_teacher()
    {
        // Caso 2 — Exclusividad por docente
        $asignaturaDoc1 = Asignatura::create(['nombre_asignatura' => 'Física']);
        Grupo::create([
            'id_asignatura' => $asignaturaDoc1->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);
        $examenDoc1 = Examen::create([
            'id_asignatura' => $asignaturaDoc1->id_asignatura,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $asignaturaDoc2 = Asignatura::create(['nombre_asignatura' => 'Química']);
        Grupo::create([
            'id_asignatura' => $asignaturaDoc2->id_asignatura,
            'id_usuario' => $this->docenteUser2->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G2',
        ]);
        $examenDoc2 = Examen::create([
            'id_asignatura' => $asignaturaDoc2->id_asignatura,
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '11:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 1)
                ->has('proximosExamenes', 1, fn (Assert $page) => $page
                    ->where('id_examen', $examenDoc1->id_examen)
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_excludes_past_exams()
    {
        // Caso 3 — Exámenes pasados
        // Caso 7 — Fecha y hora
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Biología']);
        Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);

        // Past exam (yesterday)
        Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        // Past exam (today, but past hour)
        Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->toDateString(),
            'hora_inicio' => now()->subHour()->toTimeString(),
            'duracion_minutos' => 90,
        ]);

        // Future exam
        $futureExamen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 1)
                ->has('proximosExamenes', 1, fn (Assert $page) => $page
                    ->where('id_examen', $futureExamen->id_examen)
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_excludes_exams_for_unassigned_subjects()
    {
        // Caso 4 — Exámenes de asignaturas no asignadas
        $asignaturaAsignada = Asignatura::create(['nombre_asignatura' => 'Historia']);
        Grupo::create([
            'id_asignatura' => $asignaturaAsignada->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);

        $asignaturaNoAsignada = Asignatura::create(['nombre_asignatura' => 'Geografía']);
        // Docente is NOT assigned to Geografía

        // Examen de asignatura no asignada
        Examen::create([
            'id_asignatura' => $asignaturaNoAsignada->id_asignatura,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 0)
                ->has('proximosExamenes', 0)
            );
    }

    /** @test */
    public function it_returns_empty_when_no_subjects_assigned()
    {
        // Caso 5 — Docente sin asignaturas
        // $this->docenteUser has no groups yet

        // Create random exam
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Inglés']);
        Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 0)
                ->has('proximosExamenes', 0)
            );
    }

    /** @test */
    public function it_returns_empty_when_no_upcoming_exams()
    {
        // Caso 6 — Docente sin próximos exámenes
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Literatura']);
        Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);

        // Only past exams exist
        Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 0)
                ->has('proximosExamenes', 0)
            );
    }

    /** @test */
    public function it_redirects_unauthenticated_users()
    {
        // Caso 8 — Usuario no autenticado
        $this->get(route('docente.dashboard'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function it_forbids_unauthorized_roles()
    {
        // Caso 9 — Usuario con otro rol
        // Estudiante rol doesn't have access to /docente/dashboard by default
        // The middleware is 'role:administrador,docente'

        $estudianteRol = Rol::where('nombre_rol', 'estudiante')->first() ?? Rol::create(['nombre_rol' => 'estudiante']);
        $estudianteUser = User::factory()->create(['id_rol' => $estudianteRol->id_rol]);

        $this->actingAs($estudianteUser)
            ->get(route('docente.dashboard'))
            ->assertStatus(403);

        // Admin also has access based on the middleware `role:administrador,docente`
        // Wait, does admin have access to /docente/dashboard?
        // The prompt says "Verificar que un Administrador... no pueda acceder indebidamente... salvo que el sistema tenga una regla explícita que lo autorice"
        // The rule `Route::middleware('role:administrador,docente')` explicitly authorizes both, but wait, the generic dashboard redirect is what usually handles it.
        // Actually, the route `/docente/dashboard` is in `Route::middleware('role:administrador,docente')->group(...)` so admin CAN access it.
        // We will just test that a role without permission (estudiante) gets 403.
    }

    /** @test */
    public function it_prevents_parameter_manipulation()
    {
        // Caso 10 — Manipulación de parámetros
        // Endpoint receives no parameters, we should verify that adding ?user_id=X doesn't change the outcome
        $asignaturaDoc1 = Asignatura::create(['nombre_asignatura' => 'Arte']);
        Grupo::create([
            'id_asignatura' => $asignaturaDoc1->id_asignatura,
            'id_usuario' => $this->docenteUser->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G1',
        ]);
        Examen::create([
            'id_asignatura' => $asignaturaDoc1->id_asignatura,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $asignaturaDoc2 = Asignatura::create(['nombre_asignatura' => 'Música']);
        Grupo::create([
            'id_asignatura' => $asignaturaDoc2->id_asignatura,
            'id_usuario' => $this->docenteUser2->id,
            'gestion' => '2026',
            'nombre_grupo' => 'G2',
        ]);
        Examen::create([
            'id_asignatura' => $asignaturaDoc2->id_asignatura,
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '11:00:00',
            'duracion_minutos' => 90,
        ]);

        // Attempt to pass docenteUser2's ID as a parameter to see if it leaks their exams
        $this->actingAs($this->docenteUser)
            ->get(route('docente.dashboard', ['user_id' => $this->docenteUser2->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.examenes', 1)
                ->has('proximosExamenes', 1, fn (Assert $page) => $page
                    ->where('id_asignatura', $asignaturaDoc1->id_asignatura) // Only my exam
                    ->etc()
                )
            );
    }
}
