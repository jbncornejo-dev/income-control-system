<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vínculo 1 a 1 con el estudiante cuando la cuenta pertenece a un
            // estudiante (rol 'estudiante'). Eliminar el estudiante elimina su
            // cuenta (cascade), coherente con el borrado en cascada existente.
            $table->foreignId('id_estudiante')
                ->nullable()
                ->after('id_rol')
                ->constrained('estudiante', 'id_estudiante')
                ->cascadeOnDelete();

            $table->unique('id_estudiante');

            // Contraseña inicial temporal: exige cambio en el primer ingreso.
            $table->boolean('debe_cambiar_password')->default(false)->after('password');

            // El identificador de login no es solo el correo (también código
            // universitario o documento de identidad), por lo que el correo en
            // users pasa a ser opcional.
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // El alta/importación de estudiantes puede dejar users.email en NULL
        // (estudiantes sin correo institucional). Antes de restaurar el NOT
        // NULL hay que darles un valor sintético único; si no, el rollback
        // falla por la restricción.
        DB::statement("UPDATE users SET email = 'sin-correo-' || id || '@local.invalid' WHERE email IS NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_estudiante_unique');
            $table->dropConstrainedForeignId('id_estudiante');
            $table->dropColumn('debe_cambiar_password');
            $table->string('email')->nullable(false)->change();
        });
    }
};
