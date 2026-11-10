<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo', function (Blueprint $table) {
            $table->id('id_grupo');
            $table->foreignId('id_asignatura')->constrained('asignatura', 'id_asignatura')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('users', 'id')->restrictOnDelete();
            $table->string('gestion', 20);
            $table->string('nombre_grupo', 50);

            $table->unique(['id_asignatura', 'nombre_grupo', 'gestion'], 'uq_grupo_asignatura_gestion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo');
    }
};
