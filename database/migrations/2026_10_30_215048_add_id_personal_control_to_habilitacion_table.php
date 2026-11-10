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
        Schema::table('habilitacion', function (Blueprint $table) {
            $table->foreignId('id_personal_control')
                ->nullable()
                ->after('id_examen')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habilitacion', function (Blueprint $table) {
            $table->dropForeign(['id_personal_control']);
            $table->dropColumn('id_personal_control');
        });
    }
};
