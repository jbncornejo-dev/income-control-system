<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MisExamenesTest extends TestCase
{
    use RefreshDatabase;

    protected Rol $rolEstudiante;

    protected Rol $rolDocente;

    private int $contadorEstudiantes = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->rolEstudiante = Rol::where('nombre_rol', 'estudiante')->first()
            ?? Rol::create(['nombre_rol' => 'estudiante']);
        $this->rolDocente = Rol::where('nombre_rol', 'docente')->first()
            ?? Rol::create(['nombre_rol' => 'docente']);
    }

    /** @test */
    public function el_estudiante_solo_ve_sus_examenes()
    {
        $docente = User::factory()->create(['id_rol' => $this->rolDocente->id_rol]);
        $otroDocente = User::factory()->create(['id_rol' => $this->rolDocente->id_rol]);

        $estudiante = $this->crearEstudiante('201809372');
        $otroEstudiante = $this->crearEstudiante('202100001');

        $miAsignatura = Asignatura::create(['nombre_asignatura' => 'Matemáticas']);
        $otraAsignatura = Asignatura::create(['nombre_asignatura' => 'Física']);

        $miGrupo = $this->crearGrupo($miAsignatura, $docente);
        $otroGrupo = $this->crearGrupo($otraAsignatura, $otroDocente, 'B');

        $this->inscribir($estudiante, $miGrupo);
        $this->inscribir($otroEstudiante, $otroGrupo);

        $miExamen = $this->crearExamen($miAsignatura);
        $miExamen->grupos()->attach($miGrupo->id_grupo);

        $examenDelOtro = $this->crearExamen($otraAsignatura);
        $examenDelOtro->grupos()->attach($otroGrupo->id_grupo);

        $this->actingAs($this->cuentaEstudiante($estudiante))
            ->get(route('mis-examenes.index'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/MisExamenes')
                ->where('stats.total', 1)
                ->has('examenes', 1, fn (Assert $examen) => $examen
                    ->where('id', $miExamen->id_examen)
                    ->where('asignatura', 'Matemáticas')
                    ->where('estado', 'programado')
                    ->where('grupos', ['A'])
                    ->where('hora_inicio', '08:00')
                    ->where('hora_fin', '09:30')
                    ->where('duracion', 90)
                    ->etc()
                )
            );
    }

    /** @test */
    public function el_estudiante_ve_el_examen_por_habilitacion_sin_estar_inscrito()
    {
        $estudiante = $this->crearEstudiante('201809372');
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Historia']);

        $examen = $this->crearExamen($asignatura);
        Habilitacion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => false,
            'motivo_inhabilitacion' => 'Deuda pendiente de pago en biblioteca',
            'normas_particulares' => null,
        ]);

        $this->actingAs($this->cuentaEstudiante($estudiante))
            ->get(route('mis-examenes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('examenes', 1)
                ->where('examenes.0.id', $examen->id_examen)
                ->where('examenes.0.asignatura', 'Historia')
                ->where('examenes.0.habilitacion.estado', false)
                ->where('examenes.0.habilitacion.motivo', 'Deuda pendiente de pago en biblioteca')
                ->where('examenes.0.estado', 'programado')
            );
    }

    /** @test */
    public function la_habilitacion_de_otro_estudiante_no_vincula_sus_examenes()
    {
        $estudiante = $this->crearEstudiante('201809372');
        $otroEstudiante = $this->crearEstudiante('202100001');
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Anatomía']);

        $examen = $this->crearExamen($asignatura);
        Habilitacion::create([
            'id_estudiante' => $otroEstudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => true,
        ]);

        $this->actingAs($this->cuentaEstudiante($estudiante))
            ->get(route('mis-examenes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('examenes', 0));
    }

    /** @test */
    public function el_usuario_sin_matricula_vinculada_ve_lista_vacia()
    {
        $usuario = User::factory()->create(['id_rol' => $this->rolEstudiante->id_rol]);

        $this->actingAs($usuario)
            ->get(route('mis-examenes.index'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/MisExamenes')
                ->where('estudiante', null)
                ->where('stats.total', 0)
                ->has('examenes', 0)
            );
    }

    /** @test */
    public function los_demas_roles_no_acceden_a_mis_examenes()
    {
        $docente = User::factory()->create(['id_rol' => $this->rolDocente->id_rol]);
        $adminRol = Rol::where('nombre_rol', 'administrador')->first()
            ?? Rol::create(['nombre_rol' => 'administrador']);
        $admin = User::factory()->create(['id_rol' => $adminRol->id_rol]);

        $this->actingAs($docente)->get(route('mis-examenes.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('mis-examenes.index'))->assertForbidden();
    }

    /** @test */
    public function el_orden_prioriza_en_curso_luego_proximos_luego_finalizados()
    {
        $estudiante = $this->crearEstudiante('201809372');
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación']);
        $grupo = $this->crearGrupo($asignatura, User::factory()->create(['id_rol' => $this->rolDocente->id_rol]));
        $this->inscribir($estudiante, $grupo);

        // En curso: empezó hace 30 min y dura 120 → ahora está transcurriendo.
        $inicioEnCurso = now()->subMinutes(30);
        $enCurso = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => $inicioEnCurso->toDateString(),
            'hora_inicio' => $inicioEnCurso->format('H:i:s'),
            'duracion_minutos' => 120,
        ]);

        // Próximo: mañana a las 08:00.
        $proximo = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 120,
        ]);

        // Finalizado: empezó hace 26 h y duró 60 min → terminó hace tiempo.
        $inicioFinalizado = now()->subDay()->subHours(2);
        $finalizado = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => $inicioFinalizado->toDateString(),
            'hora_inicio' => $inicioFinalizado->format('H:i:s'),
            'duracion_minutos' => 60,
        ]);

        foreach ([$enCurso, $proximo, $finalizado] as $examen) {
            $examen->grupos()->attach($grupo->id_grupo);
        }

        $this->actingAs($this->cuentaEstudiante($estudiante))
            ->get(route('mis-examenes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)
                ->where('stats.en_curso', 1)
                ->where('stats.proximos', 1)
                ->where('stats.finalizados', 1)
                ->where('examenes.0.id', $enCurso->id_examen)
                ->where('examenes.1.id', $proximo->id_examen)
                ->where('examenes.2.id', $finalizado->id_examen)
            );
    }

    private function crearEstudiante(string $codigo): Estudiante
    {
        $this->contadorEstudiantes++;

        return Estudiante::create([
            'codigo_universitario' => $codigo,
            // Documento único por estudiante (la columna es única).
            'documento_identidad' => (string) (10000000 + $this->contadorEstudiantes),
            'nombres' => 'Juan Carlos',
            'apellidos' => 'Quispe Mamani',
            'codigo_qr' => Estudiante::qrPayload($codigo),
            'email' => $codigo.'@est.umss.edu',
        ]);
    }

    private function cuentaEstudiante(Estudiante $estudiante): User
    {
        return User::factory()->create([
            'id_rol' => $this->rolEstudiante->id_rol,
            'id_estudiante' => $estudiante->id_estudiante,
        ]);
    }

    private function crearGrupo(Asignatura $asignatura, User $docente, string $nombre = 'A'): Grupo
    {
        return Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => (string) now()->year,
            'nombre_grupo' => $nombre,
        ]);
    }

    private function inscribir(Estudiante $estudiante, Grupo $grupo): void
    {
        Inscripcion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_grupo' => $grupo->id_grupo,
        ]);
    }

    private function crearExamen(Asignatura $asignatura): Examen
    {
        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'id_tipo_examen' => null,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
            // estado en NULL: el estado visible ('programado') lo deriva el
            // modelo del horario. La columna solo admite cancelado/suspendido.
        ]);
    }
}
