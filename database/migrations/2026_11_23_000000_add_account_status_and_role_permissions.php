<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'estudiante'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->boolean('activo')->default(true));
        }
        Schema::table('rol', function (Blueprint $table) {
            $table->json('permisos')->nullable();
            $table->json('asignaturas_reportes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rol', fn (Blueprint $table) => $table->dropColumn(['permisos', 'asignaturas_reportes']));
        foreach (['users', 'estudiante'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('activo'));
        }
    }
};
