<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControlTest extends TestCase
{
    use RefreshDatabase;

    protected $controlUser;

    protected $estudianteUser;

    protected $asignatura;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $controlRol = Rol::where('nombre_rol', 'personal de control de ingreso')->first() ?? Rol::create(['nombre_rol' => 'personal de control de ingreso']);
        $estudianteRol = Rol::where('nombre_rol', 'estudiante')->first() ?? Rol::create(['nombre_rol' => 'estudiante']);

        $this->controlUser = User::factory()->create(['id_rol' => $controlRol->id_rol]);
        $this->estudianteUser = User::factory()->create(['id_rol' => $estudianteRol->id_rol]);
        $this->asignatura = Asignatura::create(['nombre_asignatura' => 'General']);
    }

    /** @test */
    public function it_returns_exams_for_the_current_day()
    {
        // Caso 1 — Exámenes del día actual
        $examenHoy = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Control/Dashboard')
                ->where('stats.hoy', 1)
                ->has('examenes', 1, fn (Assert $page) => $page
                    ->where('id_examen', $examenHoy->id_examen)
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_returns_exams_for_immediate_dates()
    {
        // Caso 2 — Exámenes de fechas inmediatas (intervalo de 1 día: hoy y mañana)
        $examenManana = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 1, fn (Assert $page) => $page
                    ->where('id_examen', $examenManana->id_examen)
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_excludes_exams_outside_the_interval()
    {
        // Caso 3 — Exámenes fuera del intervalo (pasado mañana)
        $examenFueraIntervalo = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 0)
            );
    }

    /** @test */
    public function it_excludes_past_exams()
    {
        // Caso 4 — Exámenes pasados (ayer)
        $examenAyer = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->subDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 0)
            );
    }

    /** @test */
    public function it_respects_time_limits_and_ordering()
    {
        // Caso 5 — Límites de fecha y hora
        // Caso 6 — Orden cronológico
        $examenTemprano = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '07:00:00', // Inicio del día
            'duracion_minutos' => 90,
        ]);

        $examenTarde = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '23:59:00', // Final del día
            'duracion_minutos' => 90,
        ]);

        $examenManana = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '00:01:00', // Mañana temprano
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 3)
                // Orden cronológico
                ->where('examenes.0.id_examen', $examenTemprano->id_examen)
                ->where('examenes.1.id_examen', $examenTarde->id_examen)
                ->where('examenes.2.id_examen', $examenManana->id_examen)
            );
    }

    /** @test */
    public function it_returns_empty_when_no_upcoming_exams()
    {
        // Caso 7 — Sin exámenes próximos
        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 0)
            );
    }

    /** @test */
    public function it_redirects_unauthenticated_users()
    {
        // Caso 8 — Usuario no autenticado
        $this->get(route('control.dashboard'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function it_forbids_unauthorized_roles()
    {
        // Caso 9 — Usuario con rol incorrecto
        $this->actingAs($this->estudianteUser)
            ->get(route('control.dashboard'))
            ->assertStatus(403);
    }

    /** @test */
    public function it_respects_application_timezone()
    {
        // Caso 10 — Zona horaria
        $appTimezone = config('app.timezone');

        // Simular que estamos justo antes de la medianoche en la zona local
        $dateStr = '2026-05-10 23:55:00';
        Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', $dateStr, $appTimezone));

        // Examen de HOY (10 de mayo)
        $examenHoy = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-05-10',
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);

        // Examen de MAÑANA (11 de mayo)
        $examenManana = Examen::create([
            'id_asignatura' => $this->asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-05-11',
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->controlUser)
            ->get(route('control.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 2)
            );

        Carbon::setTestNow(); // Reset
    }
}
