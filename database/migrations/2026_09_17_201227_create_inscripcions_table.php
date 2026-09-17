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
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo')->cascadeOnDelete();

            $table->unique(['id_estudiante', 'id_grupo'], 'uq_estudiante_grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripcion');
    }
};
