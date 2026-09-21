<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examen', function (Blueprint $table) {
            $table->foreignId('id_periodo')
                ->nullable()
                ->constrained('periodo', 'id_periodo')
                ->restrictOnDelete();
        });

        // Respaldo de datos existentes: crea un periodo por cada año distinto
        // presente en la fecha de los exámenes (semestre 1 por defecto) y asigna
        // cada examen al periodo de su año. La asignación histórica real es
        // desconocida, por lo que se usa semestre 1 como valor determinista que
        // el administrador puede corregir después.
        DB::statement(
            "INSERT INTO periodo (gestion, semestre)
             SELECT DISTINCT to_char(fecha, 'YYYY'), 1
             FROM examen
             ON CONFLICT (gestion, semestre) DO NOTHING"
        );

        DB::statement(
            'UPDATE examen e
             SET id_periodo = p.id_periodo
             FROM periodo p
             WHERE p.gestion = to_char(e.fecha, \'YYYY\')
               AND p.semestre = 1'
        );

        // A partir de aquí todo examen debe pertenecer a un semestre.
        DB::statement('ALTER TABLE examen ALTER COLUMN id_periodo SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('examen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_periodo');
        });
    }
};
