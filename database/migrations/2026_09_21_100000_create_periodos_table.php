<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodo', function (Blueprint $table) {
            $table->id('id_periodo');
            // Año académico (ej. "2026"), consistente con "gestion" de la tabla grupo.
            $table->string('gestion', 20);
            // 1: primer semestre, 2: segundo semestre.
            $table->smallInteger('semestre');
            // Rango de fechas del semestre. Es orientativo: los exámenes de fin de
            // gestión pueden quedar fuera del rango, por eso no es obligatorio.
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            // Un mismo semestre de una gestión solo puede existir una vez.
            $table->unique(['gestion', 'semestre'], 'uq_periodo_gestion_semestre');
        });

        DB::statement(
            'ALTER TABLE periodo
             ADD CONSTRAINT periodo_semestre_chk
             CHECK (semestre IN (1, 2))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS periodo_semestre_chk');

        Schema::dropIfExists('periodo');
    }
};
