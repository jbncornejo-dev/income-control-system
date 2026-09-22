<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\TipoExamen;
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

    private function crearExamen(Asignatura $asignatura, ?int $idPeriodo = null): Examen
    {
        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $idPeriodo ?? $this->crearPeriodo()->id_periodo,
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

        // Ya no se envían opciones para un select de asignatura.
        $this->assertArrayNotHasKey('asignaturas', $respuesta->json());

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

    public function test_docente_solo_ve_examenes_que_cubren_alguna_de_sus_grupos(): void
    {
        $asignaturaPropia = $this->crearAsignatura('Cálculo');
        $examenPropio = $this->crearExamen($asignaturaPropia);
        $examenDelMismoCursoSinSuGrupo = $this->crearExamen($asignaturaPropia);
        $examenAjeno = $this->crearExamen($this->crearAsignatura('Física'));
        $docente = $this->usuario('docente');
        $grupo = $this->asignarDocente($docente, $asignaturaPropia);
        // El examen propio cubre el grupo del docente; el otro examen de la misma
        // asignatura es de otro grupo (avanza a distinto ritmo) y no debe verse.
        $examenPropio->grupos()->attach($grupo->id_grupo);

        $vistos = $this->idsExamenesVistos($docente);

        $this->assertEqualsCanonicalizing([$examenPropio->id_examen], $vistos);
        $this->assertNotContains($examenDelMismoCursoSinSuGrupo->id_examen, $vistos);
        $this->assertNotContains($examenAjeno->id_examen, $vistos);
    }

    public function test_docente_ve_los_grupos_del_examen_como_contexto(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docente = $this->usuario('docente');
        $grupoA = $this->asignarDocente($docente, $asignatura, 'A');
        $grupoB = $this->asignarDocente($docente, $asignatura, 'B');
        // El examen solo cubre los grupos que efectivamente lo rinden.
        $examen->grupos()->attach([$grupoA->id_grupo, $grupoB->id_grupo]);

        $respuesta = $this->actingAs($docente)->get('/examenes')->assertOk();

        $primerExamen = $respuesta->json('examenes.data.0');
        $this->assertSame($examen->id_examen, $primerExamen['id_examen']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $primerExamen['grupos']);
        // El docente no recibe el campo "docentes".
        $this->assertArrayNotHasKey('docentes', $primerExamen);
    }

    public function test_administrador_ve_grupos_y_docentes_de_los_grupos_del_examen(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docenteA = $this->usuario('docente');
        $docenteB = $this->usuario('docente');
        $grupoA = $this->asignarDocente($docenteA, $asignatura, 'A');
        $grupoB = $this->asignarDocente($docenteB, $asignatura, 'B');
        // El examen cubre ambos grupos, dictados por docentes distintos.
        $examen->grupos()->attach([$grupoA->id_grupo, $grupoB->id_grupo]);

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
        $grupo = $this->asignarDocente($docente, $asignaturaPropia);
        $examenPropio->grupos()->attach($grupo->id_grupo);

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

    public function test_administrador_filtra_por_fecha(): void
    {
        $examenA = $this->crearExamen($this->crearAsignatura('Cálculo'));
        $examenB = $this->crearExamen($this->crearAsignatura('Física'));
        $examenB->update(['fecha' => now()->addDays(10)->toDateString()]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get('/examenes?fecha='.$examenA->fecha)
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$examenA->id_examen],
            collect($respuesta->json('examenes.data'))->pluck('id_examen')->all()
        );
    }

    public function test_lista_incluye_la_hora_de_finalizacion_calculada(): void
    {
        // 09:00 + 90 minutos = 10:30.
        $this->crearExamen($this->crearAsignatura('Cálculo'));

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertSame('10:30', $respuesta->json('examenes.data.0.hora_fin'));
    }

    public function test_busqueda_por_asignatura_ignora_acentos_y_mayusculas(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo II');
        $examen = $this->crearExamen($asignatura);

        $admin = $this->usuario('administrador');

        // Sin acento, todo en mayúsculas, con acento y con espacios alrededor.
        foreach (['calculo', 'CALCULO', 'Cálculo', ' cALcuLO '] as $texto) {
            $respuesta = $this->actingAs($admin)
                ->get('/examenes?asignatura='.urlencode($texto))
                ->assertOk();
            $this->assertEqualsCanonicalizing(
                [$examen->id_examen],
                collect($respuesta->json('examenes.data'))->pluck('id_examen')->all(),
                "La búsqueda \"{$texto}\" debería encontrar el examen de la asignatura."
            );
        }

        // Un término que no coincida no debe devolver el examen.
        $respuesta = $this->actingAs($admin)
            ->get('/examenes?asignatura=canculo')
            ->assertOk();
        $this->assertSame([], collect($respuesta->json('examenes.data'))->pluck('id_examen')->all());
    }

    public function test_administrador_filtra_examenes_por_periodo(): void
    {
        $periodoA = $this->crearPeriodo('2026', 1);
        $periodoB = $this->crearPeriodo('2026', 2);
        $examenA = $this->crearExamen($this->crearAsignatura('Cálculo'), $periodoA->id_periodo);
        $examenB = $this->crearExamen($this->crearAsignatura('Física'), $periodoB->id_periodo);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get('/examenes?id_periodo='.$periodoA->id_periodo)
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$examenA->id_examen],
            collect($respuesta->json('examenes.data'))->pluck('id_examen')->all()
        );
        $this->assertNotContains($examenB->id_examen, collect($respuesta->json('examenes.data'))->pluck('id_examen')->all());
    }

    public function test_docente_filtra_por_periodo_solo_dentro_de_sus_examenes(): void
    {
        $periodoA = $this->crearPeriodo('2026', 1);
        $periodoB = $this->crearPeriodo('2026', 2);
        $asignaturaPropia = $this->crearAsignatura('Cálculo');
        $examenPropio = $this->crearExamen($asignaturaPropia, $periodoA->id_periodo);
        $examenOtroPeriodo = $this->crearExamen($asignaturaPropia, $periodoB->id_periodo);
        $docente = $this->usuario('docente');
        $grupo = $this->asignarDocente($docente, $asignaturaPropia);
        // Solo el examen del periodo A cubre el grupo del docente: el otro examen
        // es de otro grupo (distinto ritmo) y no debe aparecer al filtrar.
        $examenPropio->grupos()->attach($grupo->id_grupo);

        $respuesta = $this->actingAs($docente)
            ->get('/examenes?id_periodo='.$periodoA->id_periodo)
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$examenPropio->id_examen],
            collect($respuesta->json('examenes.data'))->pluck('id_examen')->all()
        );
        $this->assertNotContains($examenOtroPeriodo->id_examen, collect($respuesta->json('examenes.data'))->pluck('id_examen')->all());
    }

    public function test_el_listado_incluye_el_codigo_del_periodo(): void
    {
        $this->crearExamen($this->crearAsignatura('Cálculo'));

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertSame(
            'I-2026',
            $respuesta->json('examenes.data.0.periodo_codigo')
        );
    }

    public function test_index_incluye_la_lista_de_periodos_para_el_filtro(): void
    {
        $periodo = $this->crearPeriodo('2026', 1);

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$periodo->id_periodo],
            collect($respuesta->json('periodos'))->pluck('id_periodo')->all()
        );
    }

    public function test_docente_puede_gestionar_un_examen_cuyos_grupos_le_pertenecen(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docente = $this->usuario('docente');
        $grupoA = $this->asignarDocente($docente, $asignatura, 'A');
        $grupoB = $this->asignarDocente($docente, $asignatura, 'B');
        $examen->grupos()->attach([$grupoA->id_grupo, $grupoB->id_grupo]);

        $respuesta = $this->actingAs($docente)->get('/examenes')->assertOk();

        $this->assertTrue($respuesta->json('examenes.data.0.puede_gestionar'));
    }

    public function test_docente_no_puede_gestionar_un_examen_compartido_con_otro_docente(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docente = $this->usuario('docente');
        $otroDocente = $this->usuario('docente');
        $grupoPropio = $this->asignarDocente($docente, $asignatura, 'A');
        $grupoAjeno = $this->asignarDocente($otroDocente, $asignatura, 'C');
        $examen->grupos()->attach([$grupoPropio->id_grupo, $grupoAjeno->id_grupo]);

        $respuesta = $this->actingAs($docente)->get('/examenes')->assertOk();

        $this->assertFalse($respuesta->json('examenes.data.0.puede_gestionar'));
    }

    public function test_administrador_siempre_puede_gestionar(): void
    {
        $asignatura = $this->crearAsignatura('Cálculo');
        $examen = $this->crearExamen($asignatura);
        $docenteA = $this->usuario('docente');
        $docenteB = $this->usuario('docente');
        $grupoA = $this->asignarDocente($docenteA, $asignatura, 'A');
        $grupoB = $this->asignarDocente($docenteB, $asignatura, 'B');
        $examen->grupos()->attach([$grupoA->id_grupo, $grupoB->id_grupo]);

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertTrue($respuesta->json('examenes.data.0.puede_gestionar'));
    }

    public function test_index_incluye_la_lista_de_tipos_para_el_filtro(): void
    {
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'PP']);

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$tipo->id_tipo_examen],
            collect($respuesta->json('tipos'))->pluck('id_tipo_examen')->all()
        );
    }

    public function test_administrador_filtra_examenes_por_tipo_de_examen(): void
    {
        $primerParcial = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'PP']);
        $segundoParcial = TipoExamen::create(['nombre' => 'Segundo parcial', 'codigo' => 'SP']);
        $examenA = $this->crearExamen($this->crearAsignatura('Cálculo'));
        $examenB = $this->crearExamen($this->crearAsignatura('Física'));
        $examenA->update(['id_tipo_examen' => $primerParcial->id_tipo_examen]);
        $examenB->update(['id_tipo_examen' => $segundoParcial->id_tipo_examen]);

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get('/examenes?id_tipo_examen='.$primerParcial->id_tipo_examen)
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$examenA->id_examen],
            collect($respuesta->json('examenes.data'))->pluck('id_examen')->all()
        );
        $this->assertNotContains($examenB->id_examen, collect($respuesta->json('examenes.data'))->pluck('id_examen')->all());
    }

    public function test_el_listado_incluye_el_nombre_del_tipo_de_examen(): void
    {
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'PP']);
        $examen = $this->crearExamen($this->crearAsignatura('Cálculo'));
        $examen->update(['id_tipo_examen' => $tipo->id_tipo_examen]);

        $respuesta = $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertOk();

        $this->assertSame('Primer parcial', $respuesta->json('examenes.data.0.tipo.nombre'));
    }
}
