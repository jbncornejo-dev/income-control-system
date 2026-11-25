<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examen', function (Blueprint $table) {
            // Estado manual (decisión administrativa): 'cancelado' o 'suspendido'.
            // NULL significa que el estado se calcula automático según el horario.
            $table->string('estado', 20)->nullable();
        });

        DB::statement(
            "ALTER TABLE examen
             ADD CONSTRAINT examen_estado_chk
             CHECK (estado IS NULL OR estado IN ('cancelado', 'suspendido'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT IF EXISTS examen_estado_chk');

        Schema::table('examen', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
