<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstudianteInertiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        $this->actingAs(User::factory()->create());
    }

    public function test_index_exposes_editable_fields_for_the_edit_form(): void
    {
        $estudiante = Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-1',
        ]);

        $this->get(route('estudiantes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Index')
                ->where('estudiantes.data.0.id', $estudiante->id_estudiante)
                ->where('estudiantes.data.0.nombres', 'Ana')
                ->where('estudiantes.data.0.apellidos', 'Perez')
                ->where('estudiantes.data.0.codigo_universitario', '2020-00001')
                ->where('estudiantes.data.0.documento_identidad', '1111111')
                ->where('estudiantes.data.0.codigo_qr', 'QR-1'));
    }

    public function test_update_shares_success_message_and_persists_changes(): void
    {
        $estudiante = Estudiante::create([
            'codigo_universitario' => '2020-00001',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => 'QR-1',
        ]);

        $this->from('/estudiantes')->put(
            route('estudiantes.update', $estudiante),
            ['nombres' => 'Ana Maria', 'apellidos' => 'Perez Lopez', 'codigo_qr' => 'QR-NUEVO']
        )->assertRedirect('/estudiantes');

        $this->get('/estudiantes')->assertInertia(fn (Assert $page) => $page
            ->component('Estudiantes/Index')
            ->where('estudiantes.data.0.nombres', 'Ana Maria')
            ->where('estudiantes.data.0.apellidos', 'Perez Lopez')
            // El QR ya no se modifica a través del CRUD.
            ->where('estudiantes.data.0.codigo_qr', 'QR-1')
            ->where('flash.success', 'Estudiante actualizado correctamente.'));
    }
}
