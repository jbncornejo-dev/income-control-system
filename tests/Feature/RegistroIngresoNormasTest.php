<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistroIngresoNormasTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $rol): User
    {
        $registro = Rol::firstOrCreate(['nombre_rol' => $rol]);

        return User::create([
            'id_rol' => $registro->id_rol,
            'name' => 'Usuario '.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => Hash::make('pass'),
            'email_verified_at' => now(),
        ]);
    }

    private function examen(?string $normasGenerales = null): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()]);

        $periodo = Periodo::firstOrCreate(
            ['gestion' => (int) now()->format('Y'), 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => now()->startOfYear()->toDateString(), 'fecha_fin' => now()->endOfYear()->toDateString()]
        );

        $inicio = now()->subMinutes(10);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $periodo->id_periodo,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:s'),
            'duracion_minutos' => 120,
            'normas_generales' => $normasGenerales,
        ]);
    }

    private function estudiante(): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => 'COD'.random_int(100000, 999999),
            'documento_identidad' => (string) random_int(1000000, 9999999),
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
        ]);
    }

    private function habilitar(Examen $examen, Estudiante $estudiante, ?string $normasParticulares = null): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => true,
            'normas_particulares' => $normasParticulares,
        ]);
    }

    private function validar(Examen $examen, Estudiante $estudiante): TestResponse
    {
        return $this->actingAs($this->usuarioConRol('personal de control de ingreso'))
            ->postJson(route('registro-ingreso.validar'), [
                'id_examen' => $examen->id_examen,
                'dato_estudiante' => $estudiante->codigo_universitario,
            ]);
    }

    public function test_devuelve_las_normas_generales_del_examen(): void
    {
        $examen = $this->examen('Prohibido el uso de celulares.');
        $estudiante = $this->estudiante();
        $this->habilitar($examen, $estudiante);

        $this->validar($examen, $estudiante)
            ->assertOk()
            ->assertJsonPath('normas_generales', 'Prohibido el uso de celulares.');
    }

    public function test_devuelve_las_normas_particulares_del_estudiante(): void
    {
        $examen = $this->examen('Prohibido el uso de celulares.');
        $estudiante = $this->estudiante();
        $this->habilitar($examen, $estudiante, 'Puede usar calculadora adicional.');

        $this->validar($examen, $estudiante)
            ->assertOk()
            ->assertJsonPath('normas_generales', 'Prohibido el uso de celulares.')
            ->assertJsonPath('normas_particulares', 'Puede usar calculadora adicional.');
    }

    public function test_devuelve_null_cuando_no_se_cargaron_normas(): void
    {
        $examen = $this->examen();
        $estudiante = $this->estudiante();
        $this->habilitar($examen, $estudiante);

        $this->validar($examen, $estudiante)
            ->assertOk()
            ->assertJsonPath('normas_generales', null)
            ->assertJsonPath('normas_particulares', null);
    }

    public function test_devuelve_null_cuando_las_normas_particulares_estan_vacias(): void
    {
        $examen = $this->examen('Prohibido el uso de celulares.');
        $estudiante = $this->estudiante();
        $this->habilitar($examen, $estudiante, '');

        $this->validar($examen, $estudiante)
            ->assertOk()
            ->assertJsonPath('normas_particulares', null);
    }
}
