<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AsignaturaInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        $this->actingAs(User::factory()->create());
    }

    public function test_inertia_navigation_receives_real_data_filters_and_role(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $version = $this->get(route('asignaturas.index'))->inertiaPage()['version'];
        $this->get(route('asignaturas.index', ['nombre_asignatura' => 'cálculo']), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])
            ->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Asignaturas/Index')
            ->assertJsonPath('props.asignaturas.data.0.id_asignatura', $asignatura->id_asignatura)
            ->assertJsonPath('props.asignaturas.data.0.nombre_asignatura', 'Cálculo I')
            ->assertJsonPath('props.filtros.nombre_asignatura', 'cálculo')
            ->assertJsonPath('props.auth.user.rol', 'administrador');
    }

    public function test_create_edit_and_delete_redirect_to_updated_page_with_messages(): void
    {
        $this->from('/asignaturas')->post(route('asignaturas.store'), ['nombre_asignatura' => 'Cálculo I'])
            ->assertRedirect('/asignaturas');
        $asignatura = Asignatura::sole();
        $this->get('/asignaturas')->assertInertia(fn (Assert $page) => $page
            ->component('Asignaturas/Index')
            ->where('asignaturas.data.0.nombre_asignatura', 'Cálculo I')
            ->where('flash.success', 'Asignatura registrada correctamente.'));

        $this->from('/asignaturas')->patch(route('asignaturas.update', $asignatura), ['nombre_asignatura' => 'Cálculo II'])
            ->assertRedirect('/asignaturas');
        $this->get('/asignaturas')->assertInertia(fn (Assert $page) => $page
            ->where('asignaturas.data.0.nombre_asignatura', 'Cálculo II')
            ->where('flash.success', 'Asignatura actualizada correctamente.'));

        $this->delete(route('asignaturas.destroy', $asignatura))->assertRedirect('/asignaturas');
        $this->get('/asignaturas')->assertInertia(fn (Assert $page) => $page
            ->where('asignaturas.total', 0)
            ->where('flash.success', 'Asignatura eliminada correctamente.'));
    }

    public function test_duplicate_error_is_shared_with_the_form(): void
    {
        Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        $this->from('/asignaturas')->post(route('asignaturas.store'), ['nombre_asignatura' => 'Cálculo I'])
            ->assertRedirect('/asignaturas');
        $this->get('/asignaturas')->assertInertia(fn (Assert $page) => $page
            ->where('errors.nombre_asignatura', 'Ya existe una asignatura con ese nombre')
            ->where('asignaturas.total', 1));
    }

    public function test_blocked_delete_shares_error_instead_of_success_and_preserves_row(): void
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Cálculo I']);
        Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 60,
        ]);
        $this->from('/asignaturas')->delete(route('asignaturas.destroy', $asignatura))
            ->assertRedirect('/asignaturas');
        $this->get('/asignaturas')->assertInertia(fn (Assert $page) => $page
            ->where('flash.error', 'No se puede eliminar la asignatura porque tiene exámenes registrados')
            ->where('flash.success', null)
            ->where('asignaturas.data.0.id_asignatura', $asignatura->id_asignatura));
    }
}
