<?php

namespace Database\Seeders;

use App\Models\AuditoriaLog;
use App\Models\ConflictoIngreso;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\ExamenQr;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\RegistroIngreso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoHUsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $ahora = Carbon::now(config('app.timezone', 'America/La_Paz'));
        $control = User::where('username', 'control')->first();
        $admin = User::query()->where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->where('nombre_rol', 'administrador'))
            ->orderBy('id')->first();
        $estudiantes = Estudiante::query()->orderBy('id_estudiante')->take(6)->get();
        $examen = Examen::query()
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->orderBy('id_examen')
            ->first();

        if (! $control || $estudiantes->count() < 3 || ! $examen) {
            return;
        }

        // El primer examen queda en curso al ejecutar el seeder, para que las
        // HUs de QR, ingreso e incidencias puedan demostrarse inmediatamente.
        $inicio = $ahora->copy()->subMinutes(5);
        $examen->update([
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:s'),
            'duracion_minutos' => 120,
            'normas_generales' => 'Escenario demo HUs: documento vigente y fotografía verificable.',
        ]);
        $examen->refresh();

        $ambiente = ExamenAmbiente::query()
            ->where('id_examen', $examen->id_examen)
            ->orderBy('id_examen_ambiente')
            ->first();

        if (! $ambiente) {
            return;
        }

        $this->seedPhotos($estudiantes);
        $habilitaciones = $this->seedHabilitaciones($examen, $estudiantes, $control);
        $this->seedQrHistory($habilitaciones->first(), $examen, $ahora);
        $this->seedIngresos($ambiente, $estudiantes, $control, $ahora);
        $this->seedIncidencia($examen, $estudiantes->get(1), $control, $ahora);
        $this->seedConflict($ambiente, $estudiantes->get(2), $control, $ahora);
        if ($admin) {
            $this->seedAudit($examen, $admin, $ahora);
        }
    }

    private function seedPhotos($estudiantes): void
    {
        foreach ($estudiantes->take(3) as $estudiante) {
            $estudiante->update([
                'foto_url' => '/paseoUniversitario.jpg',
                'foto_referencia' => $estudiante->foto_referencia ?: '/paseoUniversitario.jpg',
                'puede_regenerar_qr' => true,
            ]);
        }
    }

    private function seedHabilitaciones(Examen $examen, $estudiantes, User $control)
    {
        return $estudiantes->map(function (Estudiante $estudiante, int $index) use ($examen, $control) {
            return Habilitacion::updateOrCreate(
                [
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_examen' => $examen->id_examen,
                ],
                [
                    'id_personal_control' => $control->id,
                    'estado_habilitado' => $index !== 5,
                    'motivo_inhabilitacion' => $index === 5
                        ? 'Caso demo: deuda pendiente de biblioteca.'
                        : null,
                ]
            );
        });
    }

    private function seedQrHistory(?Habilitacion $habilitacion, Examen $examen, Carbon $ahora): void
    {
        if (! $habilitacion) {
            return;
        }

        ExamenQr::firstOrCreate(
            ['token' => 'examen:v1:demo-expirado-'.$examen->id_examen],
            [
                'id_habilitacion' => $habilitacion->id_habilitacion,
                'id_estudiante' => $habilitacion->id_estudiante,
                'id_examen' => $examen->id_examen,
                'metadata' => [
                    'id_examen' => $examen->id_examen,
                    'motivo' => 'QR demo expirado e invalidado',
                ],
                'expires_at' => $ahora->copy()->subHour(),
                'invalidated_at' => $ahora->copy()->subHour(),
            ]
        );

        ExamenQr::paraHabilitacion($habilitacion);
    }

    private function seedIngresos(
        ExamenAmbiente $ambiente,
        $estudiantes,
        User $control,
        Carbon $ahora
    ): void {
        foreach ($estudiantes->take(2) as $index => $estudiante) {
            RegistroIngreso::firstOrCreate(
                [
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_examen_ambiente' => $ambiente->id_examen_ambiente,
                ],
                [
                    'id_usuario' => $control->id,
                    'fecha_hora_ingreso' => $ahora->copy()->subMinutes(3 + $index),
                ]
            );
        }
    }

    private function seedIncidencia(
        Examen $examen,
        ?Estudiante $estudiante,
        User $control,
        Carbon $ahora
    ): void {
        if (! $estudiante) {
            return;
        }

        Incidencia::firstOrCreate(
            [
                'id_examen' => $examen->id_examen,
                'id_estudiante' => $estudiante->id_estudiante,
                'tipo_incidencia' => 'Problema de identificación',
            ],
            [
                'id_usuario' => $control->id,
                'descripcion_motivo' => 'Demo HU: la fotografía requiere comparación manual con el documento.',
                'fecha_hora' => $ahora->copy()->subMinutes(2),
            ]
        );
    }

    private function seedConflict(
        ExamenAmbiente $ambiente,
        Estudiante $estudiante,
        User $control,
        Carbon $ahora
    ): void {
        ConflictoIngreso::firstOrCreate(
            ['uuid_offline' => '11111111-1111-4111-8111-111111111111'],
            [
                'id_estudiante' => $estudiante->id_estudiante,
                'id_examen_ambiente' => $ambiente->id_examen_ambiente,
                'id_usuario' => $control->id,
                'fecha_hora_intento' => $ahora->copy()->subMinute(),
                'motivo' => 'Demo HU offline: registro duplicado detectado durante sincronización.',
            ]
        );
    }

    private function seedAudit(Examen $examen, User $admin, Carbon $ahora): void
    {
        AuditoriaLog::firstOrCreate(
            [
                'id_usuario' => $admin->id,
                'tabla_afectada' => 'examen',
                'id_registro_afectado' => $examen->id_examen,
                'accion' => 'UPDATE',
            ],
            [
                'datos_anteriores' => [
                    'hora_inicio' => $ahora->copy()->subMinutes(6)->format('H:i:s'),
                    'duracion_minutos' => 90,
                    'normas_generales' => null,
                ],
                'datos_nuevos' => [
                    'hora_inicio' => $examen->hora_inicio,
                    'duracion_minutos' => $examen->duracion_minutos,
                    'normas_generales' => $examen->normas_generales,
                ],
                'fecha_hora' => $ahora,
            ]
        );
    }
}
