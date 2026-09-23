<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use Illuminate\Database\Seeder;

class EstudianteSeeder extends Seeder
{
    public function run(): void
    {
        $estudiantes = [
            ['4829135', 'Juan Carlos', 'Quispe Mamani'],
            ['5128390', 'María Fernanda', 'Gonzáles Rojas'],
            ['4987214', 'Luis Alberto', 'Condori Vargas'],
            ['5203148', 'Ana Lucía', 'Pérez Flores'],
            ['4736120', 'Pedro Miguel', 'Choque Huanca'],
            ['5364891', 'Carla Daniela', 'Rodríguez Lima'],
            ['4918325', 'Diego Alejandro', 'Mamani Callisaya'],
            ['5274013', 'Valeria Sofía', 'Torrez Ávila'],
            ['5028493', 'Andrés Felipe', 'Flores Gutiérrez'],
            ['5182746', 'Camila Andrea', 'Siles Mendoza'],
            ['4859172', 'Jorge Luis', 'Vargas Ríos'],
            ['5310284', 'Nicole Alexandra', 'Calvo Taborga'],
            ['4962347', 'Rodrigo Antonio', 'Balderrama Velasco'],
            ['5148276', 'Paola Belén', 'Castillo Paredes'],
            ['5079143', 'Cristhian Gabriel', 'Ortiz Salazar'],
            ['5236810', 'Evelyn Jazmín', 'Salinas Cornejo'],
            ['4895261', 'Marcos David', 'Romero Delgado'],
            ['5160482', 'Fernanda Abigail', 'Alconz Sánchez'],
            ['4749183', 'Sebastián Andrés', 'Cruz Velarde'],
            ['5283709', 'Daniela Noemí', 'Ferrufino Rocha'],
            ['5014827', 'Raúl Iván', 'Rojas Espinoza'],
            ['5347165', 'Mónica Estefanía', 'Villca Cárdenas'],
            ['4882394', 'Emilio Gabriel', 'Uzeda Fernández'],
            ['5190473', 'Katherine Anahí', 'Montaño Ribera'],
            ['4937286', 'Óscar Ramiro', 'Quintanilla Herrera'],
            ['5261948', 'Lucía Valentina', 'Miranda Villarroel'],
            ['5056271', 'Gabriel Alejandro', 'Mariscal Padilla'],
            ['5129813', 'Jhoselin Nicole', 'Ballivián Aguirre'],
            ['4873065', 'Leonardo Fabio', 'Arias Cuéllar'],
            ['5218572', 'Romina Fernanda', 'Pizarro Antelo'],
        ];

        // Cohortes por año de ingreso: los 4 primeros dígitos del código SIS
        // indican el año; los 5 restantes son el correlativo dentro del año.
        // Se reparten 6 estudiantes por gestión (2018 a 2022).
        foreach ($estudiantes as $indice => [$documento, $nombres, $apellidos]) {
            $anio = 2018 + intdiv($indice, 6);
            $codigo = (string) $anio.str_pad((string) (($indice % 6) + 1), 5, '0', STR_PAD_LEFT);

            Estudiante::updateOrCreate(
                ['codigo_universitario' => $codigo],
                [
                    'documento_identidad' => $documento,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'codigo_qr' => Estudiante::qrPayload($codigo),
                    // El correo institucional usa el mismo código: queda consistente.
                    'email' => $codigo.'@est.umss.edu',
                ]
            );
        }
    }
}
