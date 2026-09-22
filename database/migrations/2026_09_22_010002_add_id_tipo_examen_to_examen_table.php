<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examen', function (Blueprint $table) {
            // Los exámenes creados antes del plan de tipos quedan sin clasificar
            // (NULL); el administrador puede asignarles tipo al editarlos.
            $table->foreignId('id_tipo_examen')
                ->nullable()
                ->constrained('tipo_examen', 'id_tipo_examen')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('examen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_tipo_examen');
        });
    }
};
