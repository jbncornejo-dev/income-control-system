<?php

namespace Database\Seeders;

use App\Models\Periodo;
use Illuminate\Database\Seeder;

class PeriodoSeeder extends Seeder
{
    public function run(): void
    {
        $gestion = (string) now()->year;

        $semestres = [
            ['semestre' => 1, 'fecha_inicio' => $gestion.'-02-01', 'fecha_fin' => $gestion.'-06-30'],
            ['semestre' => 2, 'fecha_inicio' => $gestion.'-07-01', 'fecha_fin' => $gestion.'-12-20'],
        ];

        foreach ($semestres as $datos) {
            Periodo::firstOrCreate(
                ['gestion' => $gestion, 'semestre' => $datos['semestre']],
                [
                    'fecha_inicio' => $datos['fecha_inicio'],
                    'fecha_fin' => $datos['fecha_fin'],
                ]
            );
        }
    }
}
