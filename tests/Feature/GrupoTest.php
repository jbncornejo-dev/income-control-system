<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GrupoTest extends TestCase
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

    private function asignatura(): Asignatura
    {
        return Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);
    }

    public function test_el_administrador_registra_un_grupo(): void
    {
        $asignatura = $this->asignatura();
        $docente = $this->usuarioConRol('docente');

        $this->actingAs($this->usuarioConRol('administrador'))->post('/grupos', [
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grupo', [
            'id_asignatura' => $asignatura->id_asignatura,
            'nombre_grupo' => 'A',
            'gestion' => '2026',
        ]);
    }

    public function test_rechaza_grupos_duplicados_en_la_misma_asignatura_y_gestion(): void
    {
        $asignatura = $this->asignatura();
        $docente = $this->usuarioConRol('docente');
        $admin = $this->usuarioConRol('administrador');

        $datos = [
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ];

        $this->actingAs($admin)->post('/grupos', $datos);
        $this->actingAs($admin)->post('/grupos', $datos)->assertSessionHasErrors('nombre_grupo');

        $this->assertSame(1, Grupo::count());
    }

    public function test_permite_el_mismo_nombre_en_otra_asignatura_o_gestion(): void
    {
        $admin = $this->usuarioConRol('administrador');
        $docente = $this->usuarioConRol('docente');
        $primera = $this->asignatura();
        $segunda = $this->asignatura();

        $this->actingAs($admin)->post('/grupos', [
            'id_asignatura' => $primera->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/grupos', [
            'id_asignatura' => $segunda->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/grupos', [
            'id_asignatura' => $primera->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2025',
            'nombre_grupo' => 'A',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Grupo::count());
    }

    public function test_rechaza_usuarios_que_no_son_docentes(): void
    {
        $asignatura = $this->asignatura();
        $control = $this->usuarioConRol('personal de control de ingreso');

        $this->actingAs($this->usuarioConRol('administrador'))->post('/grupos', [
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $control->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ])->assertSessionHasErrors('id_usuario');

        $this->assertSame(0, Grupo::count());
    }

    public function test_rechaza_campos_obligatorios_vacios(): void
    {
        $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/grupos', [])
            ->assertSessionHasErrors(['id_asignatura', 'id_usuario', 'gestion', 'nombre_grupo']);
    }

    public function test_reasigna_el_docente_del_grupo(): void
    {
        $grupo = Grupo::create([
            'id_asignatura' => $this->asignatura()->id_asignatura,
            'id_usuario' => $this->usuarioConRol('docente')->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $nuevo = $this->usuarioConRol('docente');

        $this->actingAs($this->usuarioConRol('administrador'))
            ->patch('/grupos/'.$grupo->id_grupo, ['id_usuario' => $nuevo->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($nuevo->id, $grupo->fresh()->id_usuario);
    }

    public function test_elimina_un_grupo_sin_inscritos(): void
    {
        $grupo = Grupo::create([
            'id_asignatura' => $this->asignatura()->id_asignatura,
            'id_usuario' => $this->usuarioConRol('docente')->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $this->actingAs($this->usuarioConRol('administrador'))
            ->delete('/grupos/'.$grupo->id_grupo);

        $this->assertSame(0, Grupo::count());
    }

    public function test_no_elimina_un_grupo_con_estudiantes_inscritos(): void
    {
        $grupo = Grupo::create([
            'id_asignatura' => $this->asignatura()->id_asignatura,
            'id_usuario' => $this->usuarioConRol('docente')->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $estudiante = Estudiante::create([
            'codigo_universitario' => 'COD'.random_int(10000, 99999),
            'documento_identidad' => (string) random_int(1000000, 9999999),
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);

        Inscripcion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_grupo' => $grupo->id_grupo,
        ]);

        $this->actingAs($this->usuarioConRol('administrador'))
            ->delete('/grupos/'.$grupo->id_grupo);

        $this->assertSame(1, Grupo::count());
    }

    public function test_filtra_por_asignatura_y_gestion(): void
    {
        $admin = $this->usuarioConRol('administrador');
        $docente = $this->usuarioConRol('docente');
        $primera = $this->asignatura();
        $segunda = $this->asignatura();

        Grupo::create(['id_asignatura' => $primera->id_asignatura, 'id_usuario' => $docente->id, 'gestion' => '2026', 'nombre_grupo' => 'A']);
        Grupo::create(['id_asignatura' => $segunda->id_asignatura, 'id_usuario' => $docente->id, 'gestion' => '2025', 'nombre_grupo' => 'B']);

        $porAsignatura = $this->actingAs($admin)
            ->getJson('/grupos?id_asignatura='.$primera->id_asignatura)
            ->json('grupos.data');

        $this->assertCount(1, $porAsignatura);

        $porGestion = $this->actingAs($admin)
            ->getJson('/grupos?gestion=2025')
            ->json('grupos.data');

        $this->assertCount(1, $porGestion);
        $this->assertSame('B', $porGestion[0]['nombre_grupo']);
    }

    public function test_solo_el_administrador_accede(): void
    {
        foreach (['docente', 'personal de control de ingreso', 'estudiante'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/grupos')
                ->assertForbidden();
        }

        auth()->logout();
        $this->get('/grupos')->assertRedirect('/login');
    }
}
