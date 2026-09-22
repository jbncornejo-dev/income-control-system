<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_grupo', function (Blueprint $table) {
            $table->id('id_examen_grupo');
            $table->foreignId('id_examen')->constrained('examen', 'id_examen')->cascadeOnDelete();
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo')->cascadeOnDelete();

            $table->unique(['id_examen', 'id_grupo'], 'uq_examen_grupo');
        });

        // Compatibilidad con el modelo anterior: un examen cubría todos los grupos
        // de su asignatura. Se vinculan los exámenes existentes a esos grupos.
        DB::statement('
            INSERT INTO examen_grupo (id_examen, id_grupo)
            SELECT e.id_examen, g.id_grupo
            FROM examen e
            JOIN grupo g ON g.id_asignatura = e.id_asignatura
            ON CONFLICT DO NOTHING
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_grupo');
    }
};
