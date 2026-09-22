<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\TipoExamen;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TipoExamenPlanTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function administrador(): User
    {
        // Se cachea en la instancia: cada test llama a este helper varias veces
        // y el username/email deben ser únicos por tabla.
        return $this->admin ??= (function () {
            $rol = Rol::firstOrCreate(['nombre_rol' => 'administrador']);

            return User::create([
                'id_rol' => $rol->id_rol,
                'name' => 'Administrador',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('pass'),
            ]);
        })();
    }

    /**
     * Datos base de un examen válido, junto con el grupo creado.
     *
     * @return array{datos: array<string, mixed>, id_grupo: int}
     */
    private function datosValidos(): array
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación II']);
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 40]);

        $rolDocente = Rol::firstOrCreate(['nombre_rol' => 'docente']);
        $docente = User::create([
            'id_rol' => $rolDocente->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);

        $grupo = Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        return [
            'datos' => [
                'id_asignatura' => $asignatura->id_asignatura,
                'id_periodo' => $this->crearPeriodo()->id_periodo,
                'fecha' => now()->addDay()->format('Y-m-d'),
                'hora_inicio' => '10:00',
                'duracion_minutos' => 90,
                'id_grupos' => [$grupo->id_grupo],
                'id_ambientes' => [$ambiente->id_ambiente],
                'normas_generales' => 'Presentar documento.',
            ],
            'id_grupo' => $grupo->id_grupo,
        ];
    }

    public function test_el_administrador_puede_definir_el_plan_de_un_periodo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $primero = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $segundo = TipoExamen::create(['nombre' => 'Segundo parcial', 'codigo' => 'segundo_parcial']);

        $response = $this->actingAs($this->administrador())
            ->put("/periodos/{$periodo->id_periodo}/tipos-examen", [
                'tipos' => [
                    ['id_tipo_examen' => $primero->id_tipo_examen, 'orden' => 1],
                    ['id_tipo_examen' => $segundo->id_tipo_examen, 'orden' => 2],
                ],
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('periodo_tipo_examen', [
            'id_periodo' => $periodo->id_periodo,
            'id_tipo_examen' => $primero->id_tipo_examen,
            'orden' => 1,
        ]);
        $this->assertDatabaseHas('periodo_tipo_examen', [
            'id_periodo' => $periodo->id_periodo,
            'id_tipo_examen' => $segundo->id_tipo_examen,
        ]);

        // Un segundo PUT reemplaza el plan: el tipo no incluido se quita.
        $response = $this->actingAs($this->administrador())
            ->put("/periodos/{$periodo->id_periodo}/tipos-examen", [
                'tipos' => [
                    ['id_tipo_examen' => $segundo->id_tipo_examen, 'orden' => 1],
                ],
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('periodo_tipo_examen', [
            'id_periodo' => $periodo->id_periodo,
            'id_tipo_examen' => $primero->id_tipo_examen,
        ]);
        $this->assertDatabaseHas('periodo_tipo_examen', [
            'id_periodo' => $periodo->id_periodo,
            'id_tipo_examen' => $segundo->id_tipo_examen,
            'orden' => 1,
        ]);
    }

    public function test_el_tipo_es_obligatorio_al_crear_examen_cuando_el_periodo_tiene_plan(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $datos = $this->datosValidos()['datos'];
        unset($datos['id_tipo_examen']);

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_tipo_examen');
        $this->assertDatabaseCount('examen', 0);
    }

    public function test_el_tipo_debe_pertenecer_al_plan_del_periodo_al_crear_examen(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $ajeno = TipoExamen::create(['nombre' => 'Mesa de examen', 'codigo' => 'mesa_examen']);
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $datos = array_merge($this->datosValidos()['datos'], ['id_tipo_examen' => $ajeno->id_tipo_examen]);

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_tipo_examen');
        $this->assertDatabaseCount('examen', 0);
    }

    public function test_no_se_admite_un_tipo_cuando_el_periodo_no_tiene_plan(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);

        $datos = array_merge($this->datosValidos()['datos'], ['id_tipo_examen' => $tipo->id_tipo_examen]);

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_tipo_examen');
        $this->assertDatabaseCount('examen', 0);
    }

    public function test_el_examen_guarda_el_tipo_correcto_del_plan(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $datos = array_merge($this->datosValidos()['datos'], ['id_tipo_examen' => $tipo->id_tipo_examen]);

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('examen', [
            'id_periodo' => $periodo->id_periodo,
            'id_tipo_examen' => $tipo->id_tipo_examen,
        ]);
    }

    public function test_un_grupo_no_puede_rendir_dos_examenes_del_mismo_tipo_en_el_periodo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $datos = $this->datosValidos()['datos'];
        $datos['id_tipo_examen'] = $tipo->id_tipo_examen;

        $this->actingAs($this->administrador())->post('/examenes', $datos)->assertSessionHas('success');

        // Segundo examen del mismo tipo para el mismo grupo: otra fecha para
        // evitar el conflicto de horario y aislar la validación de tipo.
        $datos['fecha'] = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHasErrors('id_tipo_examen');
        $this->assertCount(1, Examen::where('id_tipo_examen', $tipo->id_tipo_examen)->get());
    }

    public function test_un_grupo_puede_rendir_dos_examenes_de_distintos_tipos_en_el_periodo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $primero = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $segundo = TipoExamen::create(['nombre' => 'Segundo parcial', 'codigo' => 'segundo_parcial']);
        $periodo->tiposExamen()->attach($primero->id_tipo_examen, ['orden' => 1]);
        $periodo->tiposExamen()->attach($segundo->id_tipo_examen, ['orden' => 2]);

        $datos = $this->datosValidos()['datos'];
        $datos['id_tipo_examen'] = $primero->id_tipo_examen;

        $this->actingAs($this->administrador())->post('/examenes', $datos)->assertSessionHas('success');

        $datos['id_tipo_examen'] = $segundo->id_tipo_examen;
        $datos['fecha'] = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHas('success');
        $this->assertCount(2, Examen::where('id_tipo_examen', $primero->id_tipo_examen)->orWhere('id_tipo_examen', $segundo->id_tipo_examen)->get());
    }

    public function test_grupos_distintos_pueden_rendir_examenes_del_mismo_tipo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $base = $this->datosValidos();
        $asignatura = Asignatura::where('id_asignatura', $base['datos']['id_asignatura'])->first();
        $ambiente = Ambiente::first();

        $rolDocente = Rol::firstOrCreate(['nombre_rol' => 'docente']);
        $docente = User::create([
            'id_rol' => $rolDocente->id_rol,
            'name' => 'Docente 2',
            'username' => 'docente2',
            'email' => 'docente2@example.com',
            'password' => Hash::make('pass'),
        ]);
        $otroGrupo = Grupo::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'B',
        ]);

        $datos = $base['datos'];
        $datos['id_tipo_examen'] = $tipo->id_tipo_examen;
        $this->actingAs($this->administrador())->post('/examenes', $datos)->assertSessionHas('success');

        $datos['id_grupos'] = [$otroGrupo->id_grupo];
        $datos['fecha'] = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($this->administrador())->post('/examenes', $datos);

        $response->assertSessionHas('success');
        $this->assertCount(2, Examen::where('id_tipo_examen', $tipo->id_tipo_examen)->get());
    }

    public function test_al_editar_se_rechaza_duplicar_un_tipo_ya_asignado_al_grupo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $primero = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $segundo = TipoExamen::create(['nombre' => 'Segundo parcial', 'codigo' => 'segundo_parcial']);
        $periodo->tiposExamen()->attach($primero->id_tipo_examen, ['orden' => 1]);
        $periodo->tiposExamen()->attach($segundo->id_tipo_examen, ['orden' => 2]);

        $datos = $this->datosValidos()['datos'];
        $datos['id_tipo_examen'] = $primero->id_tipo_examen;
        $this->actingAs($this->administrador())->post('/examenes', $datos)->assertSessionHas('success');

        $datos['id_tipo_examen'] = $segundo->id_tipo_examen;
        $datos['fecha'] = now()->addDays(2)->format('Y-m-d');
        $this->actingAs($this->administrador())->post('/examenes', $datos)->assertSessionHas('success');

        // Se intenta convertir el segundo examen al mismo tipo del primero.
        $segundoExamen = Examen::where('id_tipo_examen', $segundo->id_tipo_examen)->firstOrFail();

        $response = $this->actingAs($this->administrador())
            ->patch("/examenes/{$segundoExamen->id_examen}", [
                'id_tipo_examen' => $primero->id_tipo_examen,
            ]);

        $response->assertSessionHasErrors('id_tipo_examen');
        $this->assertDatabaseHas('examen', [
            'id_examen' => $segundoExamen->id_examen,
            'id_tipo_examen' => $segundo->id_tipo_examen,
        ]);
    }

    public function test_el_tipo_de_examen_queda_congelado_en_un_examen_en_curso(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $periodo = $this->crearPeriodo();
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);

        // Examen directamente en la base, con ventana en curso (hora pasada).
        $base = $this->datosValidos();
        $examen = Examen::create([
            ...$base['datos'],
            'id_tipo_examen' => $tipo->id_tipo_examen,
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => now()->subMinutes(10)->format('H:i'),
        ]);
        $examen->grupos()->attach($base['id_grupo']);
        // El examen en la base de datos no pide tipo del plan (se creó directo);
        // se agrega el plan para validar la edición de tipos normalmente.
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        $response = $this->actingAs($this->administrador())
            ->patch("/examenes/{$examen->id_examen}", [
                'id_tipo_examen' => $tipo->id_tipo_examen,
            ]);

        $response->assertSessionHasErrors('estado');
    }
}
