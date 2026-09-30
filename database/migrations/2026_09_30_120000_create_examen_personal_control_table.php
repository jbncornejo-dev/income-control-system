<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_personal_control', function (Blueprint $table) {
            $table->id('id_examen_personal');
            $table->foreignId('id_examen')->constrained('examen', 'id_examen')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('users', 'id')->cascadeOnDelete();

            $table->unique(['id_examen', 'id_usuario'], 'uq_examen_personal_control');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_personal_control');
    }
};
