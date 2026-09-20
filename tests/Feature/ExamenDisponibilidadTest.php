<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamenDisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $rol = Rol::create(['nombre_rol' => 'administrador']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    private function docente(): User
    {
        $rol = Rol::create(['nombre_rol' => 'docente']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Docente',
            'username' => 'docente',
            'email' => 'docente@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    /**
     * Crea dos ambientes y devuelve sus modelos indexados.
     *
     * @return array{aula1: Ambiente, aula2: Ambiente}
     */
    private function crearAmbientes(): array
    {
        $aula1 = Ambiente::create(['nombre_ambiente' => 'Aula 101', 'capacidad' => 40]);
        $aula2 = Ambiente::create(['nombre_ambiente' => 'Aula 102', 'capacidad' => 30]);

        return ['aula1' => $aula1, 'aula2' => $aula2];
    }

    /**
     * Crea un examen que ocupa el ambiente indicado de 10:00 a 11:30.
     */
    private function crearExamenOcupado(Ambiente $ambiente): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Programación I']);

        $examen = Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ]);

        ExamenAmbiente::create([
            'id_examen' => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);

        return $examen;
    }

    public function test_devuelve_todos_los_ambientes_disponibles_cuando_no_hay_conflictos(): void
    {
        $ambientes = $this->crearAmbientes();

        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '08:00',
            'duracion_minutos' => 60,
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'ambientes')
            ->assertJsonPath('ambientes.0.disponible', true)
            ->assertJsonPath('ambientes.1.disponible', true);
    }

    public function test_marca_ocupado_el_ambiente_solapado_en_el_mismo_horario(): void
    {
        $ambientes = $this->crearAmbientes();
        $this->crearExamenOcupado($ambientes['aula1']); // Ocupa 10:00-11:30.

        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:30',
            'duracion_minutos' => 60,
        ]);

        $response->assertOk();

        $porId = collect($response->json('ambientes'))->keyBy('id_ambiente');
        $this->assertFalse($porId[$ambientes['aula1']->id_ambiente]['disponible']);
        $this->assertTrue($porId[$ambientes['aula2']->id_ambiente]['disponible']);
    }

    public function test_horario_sin_solapamiento_deja_todos_disponibles(): void
    {
        $ambientes = $this->crearAmbientes();
        $this->crearExamenOcupado($ambientes['aula1']); // 10:00-11:30.

        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '12:00',
            'duracion_minutos' => 60,
        ]);

        $response->assertOk();

        $porId = collect($response->json('ambientes'))->keyBy('id_ambiente');
        $this->assertTrue($porId[$ambientes['aula1']->id_ambiente]['disponible']);
        $this->assertTrue($porId[$ambientes['aula2']->id_ambiente]['disponible']);
    }

    public function test_un_examen_fronterizo_que_termina_cuando_empieza_el_consulta_no_bloquea(): void
    {
        $ambientes = $this->crearAmbientes();
        $this->crearExamenOcupado($ambientes['aula1']); // 10:00-11:30.

        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '11:30',
            'duracion_minutos' => 60,
        ]);

        $response->assertOk();

        $porId = collect($response->json('ambientes'))->keyBy('id_ambiente');
        $this->assertTrue($porId[$ambientes['aula1']->id_ambiente]['disponible']);
    }

    public function test_ignora_el_examen_indicado_en_excluir_examen(): void
    {
        $ambientes = $this->crearAmbientes();
        $examen = $this->crearExamenOcupado($ambientes['aula1']); // 10:00-11:30.

        // Simula la edición de ese mismo examen: su propio ambiente no debe verse ocupado.
        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
            'excluir_examen' => $examen->id_examen,
        ]);

        $response->assertOk();

        $porId = collect($response->json('ambientes'))->keyBy('id_ambiente');
        $this->assertTrue($porId[$ambientes['aula1']->id_ambiente]['disponible']);
    }

    public function test_docente_puede_consultar_la_disponibilidad(): void
    {
        $this->crearAmbientes();

        $response = $this->actingAs($this->docente())->json('GET', '/examenes/disponibilidad', [
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '08:00',
            'duracion_minutos' => 60,
        ]);

        $response->assertOk()->assertJsonCount(2, 'ambientes');
    }

    public function test_valida_los_parametros_de_la_consulta(): void
    {
        $response = $this->actingAs($this->administrador())->json('GET', '/examenes/disponibilidad', [
            'fecha' => '10/10/2026',
            'hora_inicio' => '08:00',
            'duracion_minutos' => 60,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('fecha');
    }
}
