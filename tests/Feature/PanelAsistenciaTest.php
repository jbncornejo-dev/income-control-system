<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelAsistenciaTest extends TestCase
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

    private function examen(string $cuando = 'en_curso'): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);

        $periodo = Periodo::firstOrCreate(
            ['gestion' => (int) now()->format('Y'), 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => now()->startOfYear()->toDateString(), 'fecha_fin' => now()->endOfYear()->toDateString()]
        );

        $inicio = $cuando === 'finalizado' ? now()->subHours(5) : now()->subMinutes(10);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $periodo->id_periodo,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:s'),
            'duracion_minutos' => 120,
        ]);
    }

    private function ambiente(Examen $examen, string $nombre): ExamenAmbiente
    {
        $ambiente = Ambiente::create(['nombre_ambiente' => $nombre.' '.uniqid(), 'capacidad' => 40]);

        return ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);
    }

    private function estudiante(string $apellidos = 'Perez'): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => 'COD'.random_int(10000, 99999),
            'documento_identidad' => (string) random_int(1000000, 9999999),
            'nombres' => 'Ana',
            'apellidos' => $apellidos,
        ]);
    }

    private function habilitar(Examen $examen, Estudiante $estudiante, bool $estado = true): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => $estado,
            'motivo_inhabilitacion' => $estado ? null : 'Documentacion incompleta',
        ]);
    }

    public function test_los_tres_roles_autorizados_acceden(): void
    {
        foreach (['administrador', 'docente', 'personal de control de ingreso'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/panel-asistencia')
                ->assertOk();
        }
    }

    public function test_el_estudiante_no_accede(): void
    {
        $this->actingAs($this->usuarioConRol('estudiante'))
            ->getJson('/panel-asistencia')
            ->assertForbidden();
    }

    public function test_el_invitado_es_redirigido_al_login(): void
    {
        $this->get('/panel-asistencia')->assertRedirect('/login');
    }

    public function test_el_selector_excluye_examenes_finalizados(): void
    {
        $enCurso = $this->examen();
        $finalizado = $this->examen('finalizado');

        $ids = collect($this->actingAs($this->usuarioConRol('docente'))
            ->getJson('/panel-asistencia')
            ->json('examenes'))->pluck('id_examen');

        $this->assertTrue($ids->contains($enCurso->id_examen));
        $this->assertFalse($ids->contains($finalizado->id_examen));
    }

    public function test_separa_ingresados_por_ambiente_y_lista_pendientes(): void
    {
        $examen = $this->examen();
        $primero = $this->ambiente($examen, 'Aula');
        $segundo = $this->ambiente($examen, 'Laboratorio');

        $ingresoUno = $this->estudiante('Mamani');
        $ingresoDos = $this->estudiante('Quispe');
        $pendiente = $this->estudiante('Torrez');

        foreach ([$ingresoUno, $ingresoDos, $pendiente] as $estudiante) {
            $this->habilitar($examen, $estudiante);
        }

        $usuario = $this->usuarioConRol('personal de control de ingreso');

        RegistroIngreso::create([
            'id_estudiante' => $ingresoUno->id_estudiante,
            'id_examen_ambiente' => $primero->id_examen_ambiente,
            'id_usuario' => $usuario->id,
            'fecha_hora_ingreso' => now(),
        ]);

        RegistroIngreso::create([
            'id_estudiante' => $ingresoDos->id_estudiante,
            'id_examen_ambiente' => $segundo->id_examen_ambiente,
            'id_usuario' => $usuario->id,
            'fecha_hora_ingreso' => now(),
        ]);

        $datos = $this->actingAs($usuario)
            ->getJson('/panel-asistencia?id_examen='.$examen->id_examen)
            ->json();

        $this->assertCount(2, $datos['ambientes']);
        $this->assertSame(1, $datos['ambientes'][0]['total_ingresaron']);
        $this->assertSame(1, $datos['ambientes'][1]['total_ingresaron']);
        $this->assertCount(1, $datos['pendientes']);
        $this->assertSame($pendiente->id_estudiante, $datos['pendientes'][0]['id_estudiante']);
    }

    public function test_el_resumen_cuenta_habilitados_ingresados_y_pendientes(): void
    {
        $examen = $this->examen();
        $ambiente = $this->ambiente($examen, 'Aula');
        $usuario = $this->usuarioConRol('docente');

        $conIngreso = $this->estudiante();
        $sinIngreso = $this->estudiante();

        $this->habilitar($examen, $conIngreso);
        $this->habilitar($examen, $sinIngreso);

        RegistroIngreso::create([
            'id_estudiante' => $conIngreso->id_estudiante,
            'id_examen_ambiente' => $ambiente->id_examen_ambiente,
            'id_usuario' => $usuario->id,
            'fecha_hora_ingreso' => now(),
        ]);

        $resumen = $this->actingAs($usuario)
            ->getJson('/panel-asistencia?id_examen='.$examen->id_examen)
            ->json('resumen');

        $this->assertSame(2, $resumen['habilitados']);
        $this->assertSame(1, $resumen['ingresaron']);
        $this->assertSame(1, $resumen['pendientes']);
    }

    public function test_el_inhabilitado_no_aparece_en_ninguna_lista(): void
    {
        $examen = $this->examen();
        $ambiente = $this->ambiente($examen, 'Aula');

        $habilitado = $this->estudiante();
        $inhabilitado = $this->estudiante();

        $this->habilitar($examen, $habilitado);
        $this->habilitar($examen, $inhabilitado, false);

        $datos = $this->actingAs($this->usuarioConRol('administrador'))
            ->getJson('/panel-asistencia?id_examen='.$examen->id_examen)
            ->json();

        $pendientes = collect($datos['pendientes'])->pluck('id_estudiante');
        $ingresados = collect($datos['ambientes'][0]['ingresaron'])->pluck('id_estudiante');

        $this->assertFalse($pendientes->contains($inhabilitado->id_estudiante));
        $this->assertFalse($ingresados->contains($inhabilitado->id_estudiante));
        $this->assertSame(1, $datos['resumen']['habilitados']);
    }

    public function test_marca_como_cerrado_un_examen_finalizado(): void
    {
        $finalizado = $this->examen('finalizado');
        $enCurso = $this->examen();

        $this->ambiente($finalizado, 'Aula');
        $this->ambiente($enCurso, 'Aula');

        $usuario = $this->usuarioConRol('docente');

        $this->assertTrue(
            $this->actingAs($usuario)
                ->getJson('/panel-asistencia?id_examen='.$finalizado->id_examen)
                ->json('resumen.cerrado')
        );

        $this->assertFalse(
            $this->actingAs($usuario)
                ->getJson('/panel-asistencia?id_examen='.$enCurso->id_examen)
                ->json('resumen.cerrado')
        );
    }
}
