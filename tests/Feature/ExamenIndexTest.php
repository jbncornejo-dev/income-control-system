<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamenIndexTest extends TestCase
{
    use RefreshDatabase;

    private static int $contador = 0;

    private function usuario(string $nombreRol): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);
        $n = ++static::$contador;

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($nombreRol).' '.$n,
            'username' => str($nombreRol)->slug('_').'-'.$n,
            'email' => str($nombreRol)->slug().'-'.$n.'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    private function crearAsignatura(string $nombre): Asignatura
    {
        return Asignatura::create(['nombre_asignatura' => $nombre.' '.++static::$contador]);
    }

    private function crearExamen(Asignatura $asignatura): Examen
    {
        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '09:00',
            'duracion_minutos' => 90,
        ]);
    }

    private function asignarDocente(User $docente, Asignatura $asignatura, string $nombreGrupo = 'A'): Grupo
    {
        return Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => $nombreGrupo,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function idsExamenesVistos(User $usuario): array
    {
        $respuesta = $this->actingAs($usuario)->get('/examenes')->assertOk();

        return collect($respuesta->json('examenes.data'))
            ->pluck('id_examen')
            ->all();
    }

    public function test_administrador_ve_todos_los_examenes(): void
    {
        $examenA = $this->crearExamen($this->crearAsignatura('Cálculo'));
        $examenB = $this->crearExamen($this->crearAsignatura('Física'));

        $vistos = $this->idsExamenesVistos($this->usuario('administrador'));

        $this->assertEqualsCanonicalizing([$examenA->id_examen, $examenB->id_examen], $vistos);
    }

    public function test_docente_solo_ve_examenes_de_las_asignaturas_que_dicta(): void
    {
        $asignaturaPropia = $this->crearAsignatura('Cálculo');
        $examenPropio = $this->crearExamen($asignaturaPropia);
        $examenAjeno = $this->crearExamen($this->crearAsignatura('Física'));
        $docente = $this->usuario('docente');
        $this->asignarDocente($docente, $asignaturaPropia);

        $vistos = $this->idsExamenesVistos($docente);

        $this->assertEqualsCanonicalizing([$examenPropio->id_examen], $vistos);
        $this->assertNotContains($examenAjeno->id_examen, $vistos);
    }

    public function test_docente_ve_sus_grupos_como_contexto_en_cada_examen(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docente = $this->usuario('docente');
        $this->asignarDocente($docente, $asignatura, 'A');
        $this->asignarDocente($docente, $asignatura, 'B');

        $respuesta = $this->actingAs($docente)->get('/examenes')->assertOk();

        $primerExamen = $respuesta->json('examenes.data.0');
        $this->assertSame($examen->id_examen, $primerExamen['id_examen']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $primerExamen['grupos']);
        // El docente no recibe el campo "docentes".
        $this->assertArrayNotHasKey('docentes', $primerExamen);
    }

    public function test_administrador_ve_grupos_y_docentes_de_la_asignatura(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docenteA = $this->usuario('docente');
        $docenteB = $this->usuario('docente');
        $this->asignarDocente($docenteA, $asignatura, 'A');
        $this->asignarDocente($docenteB, $asignatura, 'B');

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $primerExamen = $respuesta->json('examenes.data.0');
        $this->assertSame($examen->id_examen, $primerExamen['id_examen']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $primerExamen['grupos']);
        $this->assertEqualsCanonicalizing(
            [$docenteA->name, $docenteB->name],
            $primerExamen['docentes']
        );
    }

    public function test_docente_sin_grupos_no_ve_ningun_examen(): void
    {
        $this->crearExamen($this->crearAsignatura('Cálculo'));

        $vistos = $this->idsExamenesVistos($this->usuario('docente'));

        $this->assertSame([], $vistos);
    }

    public function test_docente_filtra_por_asignatura_solo_dentro_de_sus_examenes(): void
    {
        $asignaturaPropia = $this->crearAsignatura('Cálculo');
        $examenPropio = $this->crearExamen($asignaturaPropia);
        $asignaturaAjena = $this->crearAsignatura('Física');
        $this->crearExamen($asignaturaAjena);
        $this->crearExamen($asignaturaAjena);

        $docente = $this->usuario('docente');
        $this->asignarDocente($docente, $asignaturaPropia);

        // Buscando la asignatura que no dicta no debe aparecer ningún examen.
        $respuesta = $this->actingAs($docente)
            ->get('/examenes?asignatura=Física')
            ->assertOk();

        $this->assertSame([], collect($respuesta->json('examenes.data'))->pluck('id_examen')->all());

        // Buscando la asignatura que dicta solo aparece su examen.
        $respuesta = $this->actingAs($docente)
            ->get('/examenes?asignatura=Cálculo')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$examenPropio->id_examen],
            collect($respuesta->json('examenes.data'))->pluck('id_examen')->all()
        );
    }
}
