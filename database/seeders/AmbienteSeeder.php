<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use Illuminate\Database\Seeder;

class AmbienteSeeder extends Seeder
{
    public function run(): void
    {
        $ambientes = [
            ['Aula 101', 40],
            ['Aula 102', 35],
            ['Aula 103', 30],
            ['Aula 104', 45],
            ['Aula 201', 50],
            ['Aula 202', 40],
            ['Laboratorio 1', 30],
            ['Auditorio Central', 60],
        ];

        foreach ($ambientes as [$nombre, $capacidad]) {
            Ambiente::updateOrCreate(
                ['nombre_ambiente' => $nombre],
                ['capacidad' => $capacidad]
            );
        }
    }
}
