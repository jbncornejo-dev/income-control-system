<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->string('foto_referencia', 255)->nullable()->after('foto_url');
            $table->boolean('puede_regenerar_qr')->default(false)->after('codigo_qr');
        });
    }

    public function down(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropColumn(['foto_referencia', 'puede_regenerar_qr']);
        });
    }
};

