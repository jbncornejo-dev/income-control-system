<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Separa la semántica de gestión en dos estados terminales:
     *
     * - 'cancelado': examen programado que se llama off (nunca ocurrió).
     * - 'anulado': examen en curso que se invalida (lo ocurrido no vale).
     *
     * La restricción CHECK de la columna `estado` se debe recrear porque
     * Postgres no permite alterar constraints en su lugar.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT IF EXISTS examen_estado_chk');

        DB::statement(
            "ALTER TABLE examen
             ADD CONSTRAINT examen_estado_chk
             CHECK (estado IS NULL OR estado IN ('cancelado', 'anulado', 'suspendido'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT IF EXISTS examen_estado_chk');

        DB::statement(
            "ALTER TABLE examen
             ADD CONSTRAINT examen_estado_chk
             CHECK (estado IS NULL OR estado IN ('cancelado', 'suspendido'))"
        );
    }
};
