<?php

namespace Database\Seeders;

use App\Models\Periodo;
use Illuminate\Database\Seeder;

class PeriodoSeeder extends Seeder
{
    public function run(): void
    {
        $gestion = (string) now()->year;

        $periodos = [
            ['tipo' => 'semestre', 'numero' => 1, 'fecha_inicio' => $gestion.'-02-01', 'fecha_fin' => $gestion.'-06-30'],
            ['tipo' => 'semestre', 'numero' => 2, 'fecha_inicio' => $gestion.'-07-01', 'fecha_fin' => $gestion.'-12-20'],
        ];

        foreach ($periodos as $datos) {
            Periodo::firstOrCreate(
                ['gestion' => $gestion, 'tipo' => $datos['tipo'], 'numero' => $datos['numero']],
                [
                    'fecha_inicio' => $datos['fecha_inicio'],
                    'fecha_fin' => $datos['fecha_fin'],
                ]
            );
        }
    }
}
