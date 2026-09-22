<?php

namespace Database\Seeders;

use App\Models\TipoExamen;
use Illuminate\Database\Seeder;

class TipoExamenSeeder extends Seeder
{
    /**
     * Tipos de examen por defecto del catálogo. No se crea plan por periodo:
     * el administrador define en el módulo "Tipos de Examen" qué tipos rigen en
     * cada gestión/periodo y con qué orden.
     */
    public function run(): void
    {
        $tipos = [
            'primer_parcial' => 'Primer parcial',
            'segundo_parcial' => 'Segundo parcial',
            'tercer_parcial' => 'Tercer parcial',
            'examen_final' => 'Examen final',
            'segunda_instancia' => 'Segunda instancia',
            'mesa_examen' => 'Mesa de examen',
        ];

        foreach ($tipos as $codigo => $nombre) {
            TipoExamen::firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true]
            );
        }
    }
}
