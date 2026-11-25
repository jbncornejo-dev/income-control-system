<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->json('datos_anteriores')->nullable()->after('accion');
            $table->json('datos_nuevos')->nullable()->after('datos_anteriores');
        });
    }

    public function down(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->dropColumn(['datos_anteriores', 'datos_nuevos']);
        });
    }
};
