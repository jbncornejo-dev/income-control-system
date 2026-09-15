<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiante', function (Blueprint $table) {
            $table->id('id_estudiante');
            $table->string('codigo_universitario', 20)->unique();
            $table->string('documento_identidad', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('codigo_qr', 255)->unique()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante');
    }
};
