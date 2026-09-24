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
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,codigo_qr\n201809372,1111111,Ana,Perez,\n";

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
            ."201809372,1111111,Ana,Perez,\n"
            ."201809373,2222222,Juan,Gomez,QR-2\n";

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
            'codigo_universitario' => '201809999',
            'documento_identidad' => '9999999',
            'nombres' => 'Existente',
            'apellidos' => 'Previo',
        ]);

        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,codigo_qr\n"
            ."201809372,1111111,Ana,Perez,\n"      // válido -> inserta
            ."201809999,2222222,Juan,Gomez,\n"     // código duplicado en BD
            ."201809374,3333333,,Lopez,\n"         // nombres vacío
            ."201809375,1111111,Carlos,Ruiz,\n"    // documento duplicado en el archivo
            ."201809376,4444444,Luisa,Mora,\n";    // válido -> inserta

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 2);
        $response->assertJsonPath('total_filas', 5);
        $response->assertJsonCount(3, 'rechazados');
        $this->assertDatabaseCount('estudiante', 3);
    }

    public function test_imports_valid_students_from_csv_with_four_columns(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos\n"
            ."201809372,1111111,Ana,Perez\n"
            ."201809373,2222222,Juan,Gomez\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 2);
        $response->assertJsonPath('total_filas', 2);
        $response->assertJsonCount(0, 'rechazados');
        // El QR no viene en el CSV de 4 columnas: se autogenera por estudiante.
        $this->assertDatabaseHas('estudiante', [
            'codigo_universitario' => '201809372',
            'codigo_qr' => Estudiante::qrPayload('201809372'),
        ]);
        $this->assertDatabaseCount('estudiante', 2);
    }

    public function test_imports_students_with_email_and_creates_access_accounts(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,email\n"
            ."201809372,1111111,Ana,Perez,201809372@est.umss.edu\n"
            ."201809373,2222222,Juan,Gomez,\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 2);
        $response->assertJsonPath('total_filas', 2);
        $response->assertJsonCount(0, 'rechazados');

        // El estudiante con correo lo conserva; el otro queda sin correo.
        $this->assertDatabaseHas('estudiante', [
            'codigo_universitario' => '201809372',
            'email' => '201809372@est.umss.edu',
        ]);
        $this->assertDatabaseHas('estudiante', [
            'codigo_universitario' => '201809373',
            'email' => null,
        ]);

        // Cada estudiante importado tiene su cuenta de acceso (rol estudiante)
        // con contraseña inicial temporal y username = código universitario.
        $this->assertDatabaseHas('users', [
            'username' => '201809372',
            'email' => '201809372@est.umss.edu',
            'debe_cambiar_password' => true,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => '201809373',
            'email' => null,
            'debe_cambiar_password' => true,
        ]);
        $this->assertDatabaseCount('users', 3); // admin + 2 cuentas de estudiante

        // La contraseña inicial es el documento de identidad (se fuerza el cambio).
        $cuenta = User::where('username', '201809372')->first();
        $this->assertTrue(Hash::check('1111111', $cuenta->password));
    }

    public function test_rejects_rows_with_invalid_field_formats(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,email\n"
            ."ABC12345,1234567,Ana,Perez,\n"          // código no SIS
            ."201809372,12,,Perez,\n"                 // CI inválido y nombres vacío
            ."201809373,1234567,Ana123,Fernandez,\n"  // nombres con caracteres inválidos
            ."201809374,1234567,Ana,Perez,ana@gmail.com\n" // correo no UMSS
            ."201809375,1234567,Ana,Perez,\n";        // válido -> inserta

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 1);
        $response->assertJsonPath('total_filas', 5);
        $response->assertJsonCount(4, 'rechazados');

        $rechazados = $response->json('rechazados');
        $motivos = array_merge(...array_column($rechazados, 'motivos'));
        $this->assertStringContainsString('9 dígitos', implode(' | ', $motivos));
        $this->assertStringContainsString('6 y 8 dígitos', implode(' | ', $motivos));
        $this->assertStringContainsString('solo pueden contener letras', implode(' | ', $motivos));
        $this->assertStringContainsString('formato válido', implode(' | ', $motivos));

        $this->assertDatabaseCount('estudiante', 1);
    }

    public function test_rejects_row_whose_email_belongs_to_another_account(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        // El correo institucional ya pertenece a otra cuenta (docente).
        User::create([
            'id_rol' => $user->id_rol,
            'name' => 'Docente',
            'username' => 'docente_x',
            'email' => '123456789@est.umss.edu',
            'password' => Hash::make('password'),
        ]);

        $csv = "codigo_universitario,documento_identidad,nombres,apellidos,email\n"
            ."201809372,1111111,Ana,Perez,123456789@est.umss.edu\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertJsonPath('exitosos', 0);
        $response->assertJsonPath('total_filas', 1);
        $response->assertJsonCount(1, 'rechazados');
        $motivos = implode(' | ', $response->json('rechazados.0.motivos'));
        $this->assertStringContainsString('otra cuenta de acceso', $motivos);
        $this->assertDatabaseCount('estudiante', 0);
        $this->assertDatabaseCount('users', 2); // admin + docente (sin cuenta de estudiante)
    }

    public function test_rejects_invalid_header(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->createUser();
        $csv = "codigo,nombre\n201809372,Ana\n";

        $response = $this->actingAs($user)->post('/estudiantes/importar', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertStatus(422);
    }
}
