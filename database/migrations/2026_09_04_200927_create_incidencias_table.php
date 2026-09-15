<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia', function (Blueprint $table) {
            $table->id('id_incidencia');
            $table->foreignId('id_estudiante')->nullable()->constrained('estudiante', 'id_estudiante')->nullOnDelete();
            $table->foreignId('id_examen')->constrained('examen', 'id_examen')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('users', 'id')->restrictOnDelete();
            $table->string('tipo_incidencia', 50);
            $table->text('descripcion_motivo');
            $table->timestampTz('fecha_hora')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia');
    }
};
