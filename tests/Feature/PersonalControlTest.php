<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonalControlTest extends TestCase
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
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);
    }

    public function test_el_administrador_asigna_personal_de_control(): void
    {
        $examen = $this->examen();
        $control = $this->usuarioConRol('personal de control de ingreso');

        $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('examen_personal_control', [
            'id_examen' => $examen->id_examen,
            'id_usuario' => $control->id,
        ]);
    }

    public function test_permite_asignar_varias_personas_al_mismo_examen(): void
    {
        $examen = $this->examen();
        $admin = $this->usuarioConRol('administrador');

        foreach ([$this->usuarioConRol('personal de control de ingreso'), $this->usuarioConRol('personal de control de ingreso')] as $control) {
            $this->actingAs($admin)
                ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $examen->fresh()->personalControl()->count());
    }

    public function test_no_permite_asignar_dos_veces_a_la_misma_persona(): void
    {
        $examen = $this->examen();
        $admin = $this->usuarioConRol('administrador');
        $control = $this->usuarioConRol('personal de control de ingreso');

        $this->actingAs($admin)
            ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id]);

        $this->actingAs($admin)
            ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id])
            ->assertSessionHasErrors('id_usuario');

        $this->assertSame(1, $examen->fresh()->personalControl()->count());
    }

    public function test_rechaza_usuarios_que_no_son_personal_de_control(): void
    {
        $examen = $this->examen();
        $docente = $this->usuarioConRol('docente');

        $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $docente->id])
            ->assertSessionHasErrors('id_usuario');

        $this->assertSame(0, $examen->fresh()->personalControl()->count());
    }

    public function test_quitar_desasigna_sin_eliminar_al_usuario(): void
    {
        $examen = $this->examen();
        $admin = $this->usuarioConRol('administrador');
        $control = $this->usuarioConRol('personal de control de ingreso');

        $examen->personalControl()->attach($control->id);

        $this->actingAs($admin)
            ->delete('/examenes/'.$examen->id_examen.'/personal-control/'.$control->id)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $examen->fresh()->personalControl()->count());
        $this->assertDatabaseHas('users', ['id' => $control->id]);
    }

    public function test_el_docente_puede_asignar(): void
    {
        $examen = $this->examen();
        $control = $this->usuarioConRol('personal de control de ingreso');

        $this->actingAs($this->usuarioConRol('docente'))
            ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $examen->fresh()->personalControl()->count());
    }

    public function test_los_roles_no_autorizados_reciben_403(): void
    {
        $examen = $this->examen();
        $control = $this->usuarioConRol('personal de control de ingreso');

        foreach (['personal de control de ingreso', 'estudiante'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => $control->id])
                ->assertForbidden();
        }
    }

    public function test_el_invitado_es_redirigido_al_login(): void
    {
        $examen = $this->examen();

        $this->post('/examenes/'.$examen->id_examen.'/personal-control', ['id_usuario' => 1])
            ->assertRedirect('/login');
    }
}
