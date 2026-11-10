<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            // Correo institucional del estudiante: se usa para la cuenta de
            // acceso (login), notificar la contraseña inicial y recuperar la
            // contraseña cuando haya infraestructura de correo configurada.
            $table->string('email', 255)->nullable()->unique()->after('apellidos');
        });
    }

    public function down(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropUnique('estudiante_email_unique');
            $table->dropColumn('email');
        });
    }
};
