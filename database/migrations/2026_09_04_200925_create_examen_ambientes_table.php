<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_ambiente', function (Blueprint $table) {
            $table->id('id_examen_ambiente');
            $table->foreignId('id_examen')->constrained('examen', 'id_examen')->cascadeOnDelete();
            $table->foreignId('id_ambiente')->constrained('ambiente', 'id_ambiente')->restrictOnDelete();
            $table->unique(['id_examen', 'id_ambiente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_ambiente');
    }
};
