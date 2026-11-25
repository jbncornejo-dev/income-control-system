<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generaliza el periodo para soportar otros calendarios académicos además
     * del semestre (trimestre, cuatrimestre, bimestre, etc.): se guarda el
     * "tipo" y un número de periodo. El semestre anterior se conserva como
     * (tipo = 'semestre', numero = valor previo).
     */
    public function up(): void
    {
        Schema::table('periodo', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable();
            $table->smallInteger('numero')->nullable();
        });

        DB::statement("UPDATE periodo SET tipo = 'semestre', numero = semestre");

        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS periodo_semestre_chk');
        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS uq_periodo_gestion_semestre');

        DB::statement('ALTER TABLE periodo ALTER COLUMN tipo SET NOT NULL');
        DB::statement('ALTER TABLE periodo ALTER COLUMN numero SET NOT NULL');

        Schema::table('periodo', function (Blueprint $table) {
            $table->dropColumn('semestre');
        });

        DB::statement(
            "ALTER TABLE periodo
             ADD CONSTRAINT periodo_tipo_chk
             CHECK (tipo IN ('semestre', 'trimestre', 'cuatrimestre', 'bimestre'))"
        );

        DB::statement('ALTER TABLE periodo ADD CONSTRAINT periodo_numero_chk CHECK (numero >= 1)');

        DB::statement(
            'ALTER TABLE periodo
             ADD CONSTRAINT uq_periodo_gestion_tipo_numero UNIQUE (gestion, tipo, numero)'
        );
    }

    public function down(): void
    {
        Schema::table('periodo', function (Blueprint $table) {
            $table->smallInteger('semestre')->nullable();
        });

        DB::statement('UPDATE periodo SET semestre = numero');

        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS uq_periodo_gestion_tipo_numero');
        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS periodo_tipo_chk');
        DB::statement('ALTER TABLE periodo DROP CONSTRAINT IF EXISTS periodo_numero_chk');

        DB::statement('ALTER TABLE periodo ALTER COLUMN semestre SET NOT NULL');

        Schema::table('periodo', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'numero']);
        });

        DB::statement(
            'ALTER TABLE periodo
             ADD CONSTRAINT uq_periodo_gestion_semestre UNIQUE (gestion, semestre)'
        );

        DB::statement(
            'ALTER TABLE periodo
             ADD CONSTRAINT periodo_semestre_chk CHECK (semestre IN (1, 2))'
        );
    }
};
