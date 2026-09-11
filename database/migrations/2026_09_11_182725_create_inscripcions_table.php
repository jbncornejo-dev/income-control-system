<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripcion', function (Blueprint $table) {
            $table->id('id_inscripcion');
            $table->foreignId('id_estudiante')->constrained('estudiante', 'id_estudiante')->cascadeOnDelete();
            $table->foreignId('id_asignatura')->constrained('asignatura', 'id_asignatura')->cascadeOnDelete();
            $table->string('gestion', 20);

            $table->unique(['id_estudiante', 'id_asignatura', 'gestion'], 'uq_estudiante_asignatura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripcion');
    }
};
