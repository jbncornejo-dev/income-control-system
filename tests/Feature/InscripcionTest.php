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

class InscripcionTest extends TestCase
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

    private function grupo(?Asignatura $asignatura = null, string $nombre = 'A', string $gestion = '2026'): Grupo
    {
        $asignatura ??= Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);

        return Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $this->usuarioConRol('docente')->id,
            'gestion' => $gestion,
            'nombre_grupo' => $nombre,
        ]);
    }

    private function estudiante(string $nombres = 'Ana', string $apellidos = 'Perez'): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => 'COD'.random_int(10000, 99999),
            'documento_identidad' => (string) random_int(1000000, 9999999),
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ]);
    }

    public function test_el_administrador_inscribe_un_estudiante(): void
    {
        $grupo = $this->grupo();
        $estudiante = $this->estudiante();

        $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/grupos/'.$grupo->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inscripcion', [
            'id_grupo' => $grupo->id_grupo,
            'id_estudiante' => $estudiante->id_estudiante,
        ]);
    }

    public function test_no_permite_inscribir_dos_veces_en_el_mismo_grupo(): void
    {
        $grupo = $this->grupo();
        $estudiante = $this->estudiante();
        $admin = $this->usuarioConRol('administrador');

        $this->actingAs($admin)->post('/grupos/'.$grupo->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante]);

        $this->actingAs($admin)
            ->post('/grupos/'.$grupo->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasErrors('id_estudiante');

        $this->assertSame(1, Inscripcion::count());
    }

    public function test_no_permite_dos_grupos_de_la_misma_asignatura_y_gestion(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);
        $primero = $this->grupo($asignatura, 'A', '2026');
        $segundo = $this->grupo($asignatura, 'B', '2026');
        $estudiante = $this->estudiante();
        $admin = $this->usuarioConRol('administrador');

        $this->actingAs($admin)->post('/grupos/'.$primero->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante]);

        $this->actingAs($admin)
            ->post('/grupos/'.$segundo->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasErrors('id_estudiante');

        $this->assertSame(1, Inscripcion::count());
    }

    public function test_permite_inscribirse_en_grupos_de_distintas_asignaturas(): void
    {
        $primero = $this->grupo();
        $segundo = $this->grupo();
        $estudiante = $this->estudiante();
        $admin = $this->usuarioConRol('administrador');

        $this->actingAs($admin)->post('/grupos/'.$primero->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/grupos/'.$segundo->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Inscripcion::count());
    }

    public function test_permite_la_misma_asignatura_en_otra_gestion(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);
        $anterior = $this->grupo($asignatura, 'A', '2025');
        $actual = $this->grupo($asignatura, 'A', '2026');
        $estudiante = $this->estudiante();
        $admin = $this->usuarioConRol('administrador');

        $this->actingAs($admin)->post('/grupos/'.$anterior->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/grupos/'.$actual->id_grupo.'/inscripciones', ['id_estudiante' => $estudiante->id_estudiante])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Inscripcion::count());
    }

    public function test_da_de_baja_sin_eliminar_al_estudiante_ni_al_grupo(): void
    {
        $grupo = $this->grupo();
        $estudiante = $this->estudiante();

        $inscripcion = Inscripcion::create([
            'id_grupo' => $grupo->id_grupo,
            'id_estudiante' => $estudiante->id_estudiante,
        ]);

        $this->actingAs($this->usuarioConRol('administrador'))
            ->delete('/grupos/'.$grupo->id_grupo.'/inscripciones/'.$inscripcion->id_inscripcion)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Inscripcion::count());
        $this->assertDatabaseHas('estudiante', ['id_estudiante' => $estudiante->id_estudiante]);
        $this->assertDatabaseHas('grupo', ['id_grupo' => $grupo->id_grupo]);
    }

    public function test_busca_estudiantes_por_codigo_y_por_apellido(): void
    {
        $grupo = $this->grupo();
        $estudiante = $this->estudiante('Lucia', 'Mamani');
        $admin = $this->usuarioConRol('administrador');

        $porApellido = $this->actingAs($admin)
            ->getJson('/grupos/'.$grupo->id_grupo.'?busqueda=Mamani')
            ->json('disponibles');

        $this->assertCount(1, $porApellido);

        $porCodigo = $this->actingAs($admin)
            ->getJson('/grupos/'.$grupo->id_grupo.'?busqueda='.$estudiante->codigo_universitario)
            ->json('disponibles');

        $this->assertCount(1, $porCodigo);
    }

    public function test_la_busqueda_excluye_a_los_ya_inscritos(): void
    {
        $grupo = $this->grupo();
        $estudiante = $this->estudiante('Lucia', 'Mamani');

        Inscripcion::create(['id_grupo' => $grupo->id_grupo, 'id_estudiante' => $estudiante->id_estudiante]);

        $disponibles = $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson('/grupos/'.$grupo->id_grupo.'?busqueda=Mamani')
            ->json('disponibles');

        $this->assertCount(0, $disponibles);
    }

    public function test_solo_el_administrador_accede_al_detalle(): void
    {
        $grupo = $this->grupo();

        foreach (['docente', 'personal de control de ingreso', 'estudiante'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/grupos/'.$grupo->id_grupo)
                ->assertForbidden();
        }

        auth()->logout();
        $this->get('/grupos/'.$grupo->id_grupo)->assertRedirect('/login');
    }
}
