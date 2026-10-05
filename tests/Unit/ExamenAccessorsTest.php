<?php

namespace Tests\Unit;

use App\Models\Examen;
use Carbon\Carbon;
use Tests\TestCase;

class ExamenAccessorsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function exam(array $attributes = []): Examen
    {
        return new Examen(array_merge([
            'fecha' => '2026-09-20',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ], $attributes));
    }

    public function test_end_time_is_start_time_plus_duration(): void
    {
        $this->assertSame('11:30', $this->exam()->hora_fin);
    }

    public function test_end_time_can_cross_midnight(): void
    {
        $exam = $this->exam(['hora_inicio' => '23:30', 'duracion_minutos' => 60]);

        $this->assertSame('00:30', $exam->hora_fin);
    }

    public function test_end_time_is_null_without_duration(): void
    {
        $this->assertNull($this->exam(['duracion_minutos' => null])->hora_fin);
    }

    public function test_schedule_status_follows_the_clock(): void
    {
        $cases = [
            '2026-09-20 09:59:00' => 'programado',
            '2026-09-20 10:00:00' => 'en_curso',
            '2026-09-20 11:29:00' => 'en_curso',
            '2026-09-20 11:30:00' => 'finalizado',
        ];

        foreach ($cases as $now => $expected) {
            Carbon::setTestNow($now);
            $this->assertSame($expected, $this->exam()->estado_horario, $now);
        }
    }

    public function test_manual_status_wins_over_the_schedule(): void
    {
        Carbon::setTestNow('2026-09-20 10:30:00');

        foreach (['cancelado', 'anulado', 'suspendido'] as $status) {
            $this->assertSame($status, $this->exam(['estado' => $status])->estado_actual);
        }
    }

    public function test_current_status_falls_back_to_the_schedule(): void
    {
        Carbon::setTestNow('2026-09-20 10:30:00');

        $this->assertSame('en_curso', $this->exam()->estado_actual);
    }

    public function test_suspended_exam_keeps_its_schedule_status(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $exam = $this->exam(['estado' => 'suspendido']);

        $this->assertSame('suspendido', $exam->estado_actual);
        $this->assertSame('finalizado', $exam->estado_horario);
    }
}