<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->unsignedBigInteger('id_registro_afectado')->nullable();

            $table->index([
                'tabla_afectada',
                'id_registro_afectado',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->dropIndex([
                'tabla_afectada',
                'id_registro_afectado',
            ]);

            $table->dropColumn('id_registro_afectado');
        });
    }
};