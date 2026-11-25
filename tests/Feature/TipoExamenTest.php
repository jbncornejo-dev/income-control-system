<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\TipoExamen;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TipoExamenTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $rolNombre): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $rolNombre]);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($rolNombre),
            'username' => str_replace(' ', '_', $rolNombre),
            'email' => str_replace(' ', '_', $rolNombre).'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    public function test_administrador_puede_registrar_un_tipo_de_examen(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($this->usuarioConRol('administrador'))
            ->post('/tipos-examen', [
                'nombre' => 'Primer parcial',
                'codigo' => 'primer_parcial',
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tipo_examen', [
            'nombre' => 'Primer parcial',
            'codigo' => 'primer_parcial',
            'activo' => true,
        ]);
    }

    public function test_no_se_admiten_nombres_ni_codigos_duplicados(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $admin = $this->usuarioConRol('administrador');

        $response = $this->actingAs($admin)
            ->post('/tipos-examen', [
                'nombre' => 'Primer parcial',
                'codigo' => 'otro_codigo',
            ]);

        $response->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('tipo_examen', 1);

        $response = $this->actingAs($admin)
            ->post('/tipos-examen', [
                'nombre' => 'Otro nombre',
                'codigo' => 'primer_parcial',
            ]);

        $response->assertSessionHasErrors('codigo');
        $this->assertDatabaseCount('tipo_examen', 1);
    }

    public function test_el_codigo_se_normaliza_a_minusculas_y_valida_formato(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->usuarioConRol('administrador');

        $respuestaInvalida = $this->actingAs($admin)
            ->post('/tipos-examen', [
                'nombre' => 'Segundo parcial',
                'codigo' => 'Segundo Parcial',
            ]);

        $respuestaInvalida->assertSessionHasErrors('codigo');

        $respuestaValida = $this->actingAs($admin)
            ->post('/tipos-examen', [
                'nombre' => 'Segundo parcial',
                'codigo' => 'SEGUNDO_PARCIAL',
            ]);

        $respuestaValida->assertSessionHas('success');
        $this->assertDatabaseHas('tipo_examen', ['codigo' => 'segundo_parcial']);
    }

    public function test_solo_el_administrador_puede_gestionar_tipos(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($this->usuarioConRol('docente'))
            ->post('/tipos-examen', ['nombre' => 'Parcial', 'codigo' => 'parcial']);

        $response->assertForbidden();
        $this->assertDatabaseCount('tipo_examen', 0);
    }

    public function test_administrador_puede_editar_un_tipo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tipo = TipoExamen::create(['nombre' => 'Parcial', 'codigo' => 'parcial']);

        $response = $this->actingAs($this->usuarioConRol('administrador'))
            ->patch("/tipos-examen/{$tipo->id_tipo_examen}", [
                'nombre' => 'Primer parcial',
                'codigo' => 'primer_parcial',
                'activo' => false,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tipo_examen', [
            'id_tipo_examen' => $tipo->id_tipo_examen,
            'nombre' => 'Primer parcial',
            'codigo' => 'primer_parcial',
            'activo' => false,
        ]);
    }

    public function test_no_se_puede_eliminar_un_tipo_en_uso_por_planes_de_periodo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $tipo = TipoExamen::create(['nombre' => 'Parcial', 'codigo' => 'parcial']);
        $periodo = $this->crearPeriodo();
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);
        $admin = $this->usuarioConRol('administrador');

        $response = $this->actingAs($admin)
            ->delete("/tipos-examen/{$tipo->id_tipo_examen}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('tipo_examen', ['id_tipo_examen' => $tipo->id_tipo_examen]);

        // Sin plan ni exámenes vinculados, el borrado sí procede.
        $tipoLibre = TipoExamen::create(['nombre' => 'Extra', 'codigo' => 'extra']);

        $respuesta = $this->actingAs($admin)
            ->delete("/tipos-examen/{$tipoLibre->id_tipo_examen}");

        $respuesta->assertSessionHas('success');
        $this->assertDatabaseMissing('tipo_examen', ['id_tipo_examen' => $tipoLibre->id_tipo_examen]);
    }

    public function test_el_listado_expone_tipos_y_periodos_con_su_plan(): void
    {
        $tipo = TipoExamen::create(['nombre' => 'Primer parcial', 'codigo' => 'primer_parcial']);
        $periodo = $this->crearPeriodo();
        $periodo->tiposExamen()->attach($tipo->id_tipo_examen, ['orden' => 1]);

        config(['inertia.testing.ensure_pages_exist' => false]);

        $response = $this->actingAs($this->usuarioConRol('administrador'))
            ->get('/tipos-examen?nombre=parcial');

        $response->assertOk();
        $response->assertJsonPath('tipos.data.0.id_tipo_examen', $tipo->id_tipo_examen);
        $response->assertJsonPath('tipos.data.0.nombre', 'Primer parcial');
        $response->assertJsonPath('periodos.0.tipos_examen.0.id_tipo_examen', $tipo->id_tipo_examen);
    }
}
