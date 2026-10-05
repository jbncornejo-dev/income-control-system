<?php

namespace Tests\Unit;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentAndUserModelTest extends TestCase
{
    public function test_university_code_needs_nine_digits_starting_with_a_valid_year(): void
    {
        foreach (['201809372', '199912345', '202600001'] as $valid) {
            $this->assertSame(1, preg_match(Estudiante::REGEX_CODIGO_SIS, $valid), $valid);
        }

        foreach (['20180937', '2018093721', '301809372', '2018abc72', ''] as $invalid) {
            $this->assertSame(0, preg_match(Estudiante::REGEX_CODIGO_SIS, $invalid), $invalid);
        }
    }

    public function test_identity_document_needs_six_to_eight_digits(): void
    {
        foreach (['123456', '1234567', '12345678'] as $valid) {
            $this->assertSame(1, preg_match(Estudiante::REGEX_DOCUMENTO_CI, $valid), $valid);
        }

        foreach (['12345', '123456789', '12a4567', ''] as $invalid) {
            $this->assertSame(0, preg_match(Estudiante::REGEX_DOCUMENTO_CI, $invalid), $invalid);
        }
    }

    public function test_names_accept_accents_hyphens_and_apostrophes_only(): void
    {
        foreach (['Ana', 'María José', "O'Connor", 'Ana-Lucía', 'Núñez'] as $valid) {
            $this->assertSame(1, preg_match(Estudiante::REGEX_NOMBRES, $valid), $valid);
        }

        foreach (['Ana3', '1Ana', ' Ana', '', 'Ana@'] as $invalid) {
            $this->assertSame(0, preg_match(Estudiante::REGEX_NOMBRES, $invalid), $invalid);
        }
    }

    public function test_qr_payload_is_prefix_plus_university_code(): void
    {
        $this->assertSame('ident:v1:201809372', Estudiante::qrPayload('201809372'));
    }

    public function test_only_student_accounts_with_the_flag_are_pending_password_change(): void
    {
        $cases = [
            [1, true, true],
            [1, false, false],
            [null, true, false],
            [null, false, false],
        ];

        foreach ($cases as [$studentId, $mustChange, $expected]) {
            $user = new User(['id_estudiante' => $studentId, 'debe_cambiar_password' => $mustChange]);
            $this->assertSame($expected, $user->esCuentaEstudiantePendiente());
        }
    }

    public function test_password_is_stored_hashed_and_hidden_from_serialization(): void
    {
        $user = new User(['name' => 'Test', 'username' => 'test', 'password' => 'secret123']);

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }
}