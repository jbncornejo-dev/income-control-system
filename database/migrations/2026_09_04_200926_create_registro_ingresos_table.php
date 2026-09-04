<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_ingreso', function (Blueprint $table) {
            $table->id('id_registro');
            $table->foreignId('id_estudiante')->constrained('estudiante', 'id_estudiante')->restrictOnDelete();
            $table->foreignId('id_examen_ambiente')->constrained('examen_ambiente', 'id_examen_ambiente')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('users', 'id')->restrictOnDelete();
            $table->timestampTz('fecha_hora_ingreso')->useCurrent();
            $table->unique(['id_estudiante', 'id_examen_ambiente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_ingreso');
    }
};
