<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaGlobalPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['inertia.testing.ensure_pages_exist' => false]);

        // Ensure there is a route that renders an inertia page to test the middleware
        // We can just use the dashboard route or login route. We'll use a test route
        // to isolate the middleware testing.
        Route::middleware(['web'])->get('/test-inertia', function () {
            return inertia('TestPage');
        });
    }

    /** @test */
    public function it_shares_authenticated_user_data_in_inertia_props()
    {
        // Caso 1, 2 y 3: Usuario autenticado, nombre del rol, permisos de menú
        $rol = Rol::create(['nombre_rol' => 'administrador']);
        $user = User::factory()->create([
            'id_rol' => $rol->id_rol,
            'username' => 'admin_user',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($user)
            ->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->component('TestPage')
                ->has('auth.user', fn (Assert $page) => $page
                    ->where('id', $user->id)
                    ->where('name', $user->name)
                    ->where('email', $user->email)
                    ->where('username', 'admin_user')
                    ->where('rol', 'administrador')
                    ->where('nombre_rol', 'administrador')
                    ->where('permisos_menu', [])
                )
            );
    }

    /** @test */
    public function it_shares_data_for_different_roles()
    {
        // Caso 4: Diferentes roles
        $rol1 = Rol::create(['nombre_rol' => 'docente']);
        $user1 = User::factory()->create(['id_rol' => $rol1->id_rol]);

        $this->actingAs($user1)
            ->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user', fn (Assert $page) => $page
                    ->where('nombre_rol', 'docente')
                    ->etc()
                )
            );

        $rol2 = Rol::create(['nombre_rol' => 'estudiante']);
        $user2 = User::factory()->create(['id_rol' => $rol2->id_rol]);

        $this->actingAs($user2)
            ->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user', fn (Assert $page) => $page
                    ->where('nombre_rol', 'estudiante')
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_handles_unauthenticated_user()
    {
        // Caso 6: Usuario no autenticado
        $this->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
            );
    }

    /** @test */
    public function it_does_not_expose_sensitive_data()
    {
        // Caso 7: No exposición de datos sensibles
        $rol = Rol::create(['nombre_rol' => 'controlador']);
        $user = User::factory()->create([
            'id_rol' => $rol->id_rol,
            'password' => bcrypt('secretpassword'),
        ]);

        $this->actingAs($user)
            ->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user', fn (Assert $page) => $page
                    ->missing('password')
                    ->missing('remember_token')
                    ->etc()
                )
            );
    }

    /** @test */
    public function it_retains_existing_global_props()
    {
        // Caso 8: Compatibilidad con props existentes
        $this->withSession(['success' => 'Task completed successfully'])
            ->get('/test-inertia')
            ->assertInertia(fn (Assert $page) => $page
                ->has('flash', fn (Assert $page) => $page
                    ->where('success', 'Task completed successfully')
                    ->where('error', null)
                )
            );
    }
}
