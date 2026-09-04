<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen', function (Blueprint $table) {
            $table->id('id_examen');
            $table->foreignId('id_asignatura')->constrained('asignatura', 'id_asignatura')->restrictOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->integer('duracion_minutos')->unsigned();
            $table->text('normas_generales')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen');
    }
};
