<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_log', function (Blueprint $table) {
            $table->id('id_log');
            $table->foreignId('id_usuario')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string('tabla_afectada', 50);
            $table->string('accion', 10);
            $table->timestampTz('fecha_hora')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_log');
    }
};
