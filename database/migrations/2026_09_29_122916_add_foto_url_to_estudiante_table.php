<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->string('foto_url', 255)->nullable()->after('apellidos');
        });
    }

    public function down(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropColumn('foto_url');
        });
    }
};