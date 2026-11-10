<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambiarPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function estudiantePendiente(): array
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'estudiante']);
        $estudiante = Estudiante::create([
            'codigo_universitario' => '201809372',
            'documento_identidad' => '1111111',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);
        $cuenta = User::create([
            'id_rol' => $rol->id_rol,
            'id_estudiante' => $estudiante->id_estudiante,
            'name' => 'Ana Perez',
            'username' => '201809372',
            'email' => null,
            'password' => Hash::make('1111111'),
            'debe_cambiar_password' => true,
        ]);

        return [$estudiante, $cuenta];
    }

    public function test_guest_cannot_see_password_change_page(): void
    {
        $this->get(route('cambiar-password.show'))->assertRedirect(route('login'));
    }

    public function test_password_change_clears_flag_and_allows_login_with_new_password(): void
    {
        [, $cuenta] = $this->estudiantePendiente();

        // El estudiante entra con su contraseña inicial y es dirigido al cambio.
        $this->post('/login', [
            'identificador' => '201809372',
            'password' => '1111111',
        ])->assertRedirect(route('cambiar-password.show'));

        $this->actingAs($cuenta)
            ->post(route('cambiar-password.store'), [
                'password_actual' => '1111111',
                'password' => 'nuevaClaveSegura',
                'password_confirmation' => 'nuevaClaveSegura',
            ])
            ->assertRedirect(route('dashboard'));

        $cuenta->refresh();
        $this->assertFalse($cuenta->debe_cambiar_password);
        $this->assertTrue(Hash::check('nuevaClaveSegura', $cuenta->password));

        // Ahora el login con la nueva contraseña va directo al dashboard.
        $this->post('/logout');
        $this->post('/login', [
            'identificador' => '201809372',
            'password' => 'nuevaClaveSegura',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_estudiante_con_password_pendiente_no_abre_el_panel_por_url(): void
    {
        [$estudiante, $cuenta] = $this->estudiantePendiente();

        // Entrar directo a /mis-examenes con el flag activo debe redirigir al
        // cambio de contraseña (no basta con el redirect del login).
        $this->actingAs($cuenta)
            ->get(route('mis-examenes.index'))
            ->assertRedirect(route('cambiar-password.show'));

        $cuenta->refresh();
        $this->assertTrue($cuenta->debe_cambiar_password);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        [, $cuenta] = $this->estudiantePendiente();

        $this->actingAs($cuenta)
            ->post(route('cambiar-password.store'), [
                'password_actual' => 'incorrecta',
                'password' => 'nuevaClaveSegura',
                'password_confirmation' => 'nuevaClaveSegura',
            ])
            ->assertSessionHasErrors('password_actual');

        $cuenta->refresh();
        $this->assertTrue($cuenta->debe_cambiar_password);
    }

    public function test_password_change_requires_minimum_length_and_confirmation(): void
    {
        [, $cuenta] = $this->estudiantePendiente();

        $this->actingAs($cuenta)
            ->post(route('cambiar-password.store'), [
                'password_actual' => '1111111',
                'password' => 'corta',
                'password_confirmation' => 'otra',
            ])
            ->assertSessionHasErrors('password');

        $cuenta->refresh();
        $this->assertTrue($cuenta->debe_cambiar_password);
    }
}
