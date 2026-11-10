<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna para idempotencia en registro_ingreso
        Schema::table('registro_ingreso', function (Blueprint $table) {
            $table->uuid('uuid_offline')->nullable()->unique();
        });

        // 2. Tabla para almacenar conflictos de sincronización concurrente
        Schema::create('conflicto_ingreso', function (Blueprint $table) {
            $table->id('id_conflicto');
            $table->uuid('uuid_offline')->nullable()->unique();
            $table->foreignId('id_estudiante')->constrained('estudiante', 'id_estudiante')->restrictOnDelete();
            $table->foreignId('id_examen_ambiente')->constrained('examen_ambiente', 'id_examen_ambiente')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('users', 'id')->restrictOnDelete();
            $table->timestampTz('fecha_hora_intento');
            $table->string('motivo', 255)->default('Registro concurrente duplicado');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflicto_ingreso');

        Schema::table('registro_ingreso', function (Blueprint $table) {
            $table->dropColumn('uuid_offline');
        });
    }
};