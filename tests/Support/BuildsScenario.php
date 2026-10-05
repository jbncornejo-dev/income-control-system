<?php

namespace Tests\Support;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait BuildsScenario
{
    private static int $studentSequence = 0;

    protected function makeUser(string $role, ?string $username = null): User
    {
        $roleModel = Rol::firstOrCreate(['nombre_rol' => $role]);
        $username ??= (string) str($role)->slug('_');

        return User::create([
            'id_rol' => $roleModel->id_rol,
            'name' => ucfirst($role),
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    protected function makeStudent(string $code, string $document): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => $code,
            'documento_identidad' => $document,
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'codigo_qr' => Estudiante::qrPayload($code),
        ]);
    }

    protected function makeExamScenario(int $studentCount = 2, ?User $teacher = null): array
    {
        $teacher ??= $this->makeUser('docente');
        $subject = Asignatura::create(['nombre_asignatura' => 'Programacion '.uniqid()]);
        $room = Ambiente::create(['nombre_ambiente' => 'Aula '.uniqid(), 'capacidad' => 40]);
        $group = Grupo::create([
            'id_asignatura' => $subject->id_asignatura,
            'id_usuario' => $teacher->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);

        $students = [];
        for ($i = 1; $i <= $studentCount; $i++) {
            $sequence = ++self::$studentSequence;
            $student = $this->makeStudent(sprintf('2026%05d', $sequence), sprintf('%07d', 1000000 + $sequence));
            Inscripcion::create(['id_estudiante' => $student->id_estudiante, 'id_grupo' => $group->id_grupo]);
            $students[] = $student;
        }

        $exam = Examen::create([
            'id_asignatura' => $subject->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-21',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ]);
        $exam->grupos()->attach($group->id_grupo);
        $examRoom = ExamenAmbiente::create(['id_examen' => $exam->id_examen, 'id_ambiente' => $room->id_ambiente]);

        return compact('teacher', 'subject', 'room', 'group', 'students', 'exam', 'examRoom');
    }
}