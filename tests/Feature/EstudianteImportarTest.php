<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EstudianteImportarTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $rol = Rol::create(['nombre_rol' => 'administrador']);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => 'Tester',
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('estudiantes.csv', $content);
    }

    public function test_guest_cannot_import_students(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,codigo_qr\n2020-00001,1111111,Ana,Perez,\n";

        $response = $this->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_imports_valid_students_from_csv(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,codigo_qr\n"
            ."2020-00001,1111111,Ana,Perez,\n"
            ."2020-00002,2222222,Juan,Gomez,QR-2\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 2);
        $response->assertJsonPath('total_filas', 2);
        $response->assertJsonCount(0, 'rechazados');
        $this->assertDatabaseCount('estudiante', 2);
    }

    public function test_rejects_invalid_and_duplicate_rows_without_stopping(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        Estudiante::create([
            'codigo_universitario' => '2020-99999',
            'documento_identidad' => '9999999',
            'nombres' => 'Existente',
            'apellidos' => 'Previo',
        ]);

        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,codigo_qr\n"
            ."2020-00001,1111111,Ana,Perez,\n"      // válido -> inserta
            ."2020-99999,2222222,Juan,Gomez,\n"     // código duplicado en BD
            ."2020-00002,3333333,,Lopez,\n"         // nombres vacío
            ."2020-00003,1111111,Carlos,Ruiz,\n"    // documento duplicado en el archivo
            ."2020-00004,4444444,Luisa,Mora,\n";    // válido -> inserta

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 2);
        $response->assertJsonPath('total_filas', 5);
        $response->assertJsonCount(3, 'rechazados');
        $this->assertDatabaseCount('estudiante', 3);
    }

    public function test_rejects_invalid_header(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo,nombre\n2020-00001,Ana\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertStatus(422);
    }
}
