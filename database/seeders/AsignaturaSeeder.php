<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use Illuminate\Database\Seeder;

class AsignaturaSeeder extends Seeder
{
    public function run(): void
    {
        $asignaturas = [
            'Cálculo II',
            'Física I',
            'Álgebra Lineal',
            'Programación I',
            'Estadística',
            'Fisiología',
            'Anatomía Humana',
            'Redacción Académica',
        ];

        foreach ($asignaturas as $nombre) {
            Asignatura::firstOrCreate(['nombre_asignatura' => $nombre]);
        }
    }
}
