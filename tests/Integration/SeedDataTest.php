<?php

namespace Tests\Integration;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_roles_are_created(): void
    {
        $this->assertEqualsCanonicalizing(
            ['administrador', 'docente', 'personal de control de ingreso', 'estudiante'],
            Rol::pluck('nombre_rol')->all()
        );
    }

    public function test_every_role_has_at_least_one_user_and_admin_exists(): void
    {
        foreach (Rol::all() as $role) {
            $this->assertGreaterThanOrEqual(1, User::where('id_rol', $role->id_rol)->count(), $role->nombre_rol);
        }

        $admin = User::where('username', 'admin')->first();
        $this->assertSame('administrador', $admin->rol->nombre_rol);
    }

    public function test_base_catalogs_are_loaded(): void
    {
        $this->assertGreaterThanOrEqual(10, Estudiante::count());
        $this->assertGreaterThanOrEqual(3, Asignatura::count());
        $this->assertGreaterThanOrEqual(2, Ambiente::count());
        $this->assertGreaterThanOrEqual(2, Examen::count());
    }

    public function test_every_seeded_exam_has_rooms_and_groups_and_some_have_enabled_students(): void
    {
        foreach (Examen::all() as $exam) {
            $this->assertGreaterThanOrEqual(1, $exam->examenesAmbientes()->count(), 'rooms exam '.$exam->id_examen);
            $this->assertGreaterThanOrEqual(1, $exam->grupos()->count(), 'groups exam '.$exam->id_examen);
        }

        $this->assertTrue(Examen::whereHas('habilitaciones')->exists());
    }

    public function test_demo_student_account_is_linked_to_a_real_student(): void
    {
        $account = User::where('username', 'estudiante')->first();

        $this->assertNotNull($account->id_estudiante);
        $this->assertSame($account->email, $account->estudiante->email);
    }

    public function test_running_the_seeders_twice_does_not_duplicate_base_data(): void
    {
        $before = [Rol::count(), User::count(), Estudiante::count(), Asignatura::count(), Ambiente::count()];

        $this->seed();

        $after = [Rol::count(), User::count(), Estudiante::count(), Asignatura::count(), Ambiente::count()];
        $this->assertSame($before, $after);
    }
}