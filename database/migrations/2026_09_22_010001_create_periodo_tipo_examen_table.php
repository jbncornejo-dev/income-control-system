<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan de evaluación por periodo: qué tipos de examen rigen en cada
        // gestión/periodo y en qué orden aparecen (pivot.orden).
        Schema::create('periodo_tipo_examen', function (Blueprint $table) {
            $table->id('id_periodo_tipo_examen');
            $table->foreignId('id_periodo')->constrained('periodo', 'id_periodo')->cascadeOnDelete();
            $table->foreignId('id_tipo_examen')->constrained('tipo_examen', 'id_tipo_examen')->cascadeOnDelete();
            $table->smallInteger('orden')->unsigned()->default(0);

            $table->unique(['id_periodo', 'id_tipo_examen'], 'uq_periodo_tipo_examen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_tipo_examen');
    }
};
