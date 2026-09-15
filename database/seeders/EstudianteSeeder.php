<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use Illuminate\Database\Seeder;

class EstudianteSeeder extends Seeder
{
    public function run(): void
    {
        $estudiantes = [
            ['2024-0001', '4829135', 'Juan Carlos', 'Quispe Mamani'],
            ['2024-0002', '5128390', 'María Fernanda', 'Gonzáles Rojas'],
            ['2024-0003', '4987214', 'Luis Alberto', 'Condori Vargas'],
            ['2024-0004', '5203148', 'Ana Lucía', 'Pérez Flores'],
            ['2024-0005', '4736120', 'Pedro Miguel', 'Choque Huanca'],
            ['2024-0006', '5364891', 'Carla Daniela', 'Rodríguez Lima'],
            ['2024-0007', '4918325', 'Diego Alejandro', 'Mamani Callisaya'],
            ['2024-0008', '5274013', 'Valeria Sofía', 'Torrez Ávila'],
            ['2024-0009', '5028493', 'Andrés Felipe', 'Flores Gutiérrez'],
            ['2024-0010', '5182746', 'Camila Andrea', 'Siles Mendoza'],
            ['2024-0011', '4859172', 'Jorge Luis', 'Vargas Ríos'],
            ['2024-0012', '5310284', 'Nicole Alexandra', 'Calvo Taborga'],
            ['2024-0013', '4962347', 'Rodrigo Antonio', 'Balderrama Velasco'],
            ['2024-0014', '5148276', 'Paola Belén', 'Castillo Paredes'],
            ['2024-0015', '5079143', 'Cristhian Gabriel', 'Ortiz Salazar'],
            ['2024-0016', '5236810', 'Evelyn Jazmín', 'Salinas Cornejo'],
            ['2024-0017', '4895261', 'Marcos David', 'Romero Delgado'],
            ['2024-0018', '5160482', 'Fernanda Abigail', 'Alconz Sánchez'],
            ['2024-0019', '4749183', 'Sebastián Andrés', 'Cruz Velarde'],
            ['2024-0020', '5283709', 'Daniela Noemí', 'Ferrufino Rocha'],
            ['2024-0021', '5014827', 'Raúl Iván', 'Rojas Espinoza'],
            ['2024-0022', '5347165', 'Mónica Estefanía', 'Villca Cárdenas'],
            ['2024-0023', '4882394', 'Emilio Gabriel', 'Uzeda Fernández'],
            ['2024-0024', '5190473', 'Katherine Anahí', 'Montaño Ribera'],
            ['2024-0025', '4937286', 'Óscar Ramiro', 'Quintanilla Herrera'],
            ['2024-0026', '5261948', 'Lucía Valentina', 'Miranda Villarroel'],
            ['2024-0027', '5056271', 'Gabriel Alejandro', 'Mariscal Padilla'],
            ['2024-0028', '5129813', 'Jhoselin Nicole', 'Ballivián Aguirre'],
            ['2024-0029', '4873065', 'Leonardo Fabio', 'Arias Cuéllar'],
            ['2024-0030', '5218572', 'Romina Fernanda', 'Pizarro Antelo'],
        ];

        foreach ($estudiantes as [$codigo, $documento, $nombres, $apellidos]) {
            Estudiante::updateOrCreate(
                ['codigo_universitario' => $codigo],
                [
                    'documento_identidad' => $documento,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                ]
            );
        }
    }
}
