<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilitacion', function (Blueprint $table) {
            $table->id('id_habilitacion');
            $table->foreignId('id_estudiante')->constrained('estudiante', 'id_estudiante')->cascadeOnDelete();
            $table->foreignId('id_examen')->constrained('examen', 'id_examen')->cascadeOnDelete();
            $table->boolean('estado_habilitado')->default(true);
            $table->text('motivo_inhabilitacion')->nullable();
            $table->text('normas_particulares')->nullable();
            $table->unique(['id_estudiante', 'id_examen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitacion');
    }
};
