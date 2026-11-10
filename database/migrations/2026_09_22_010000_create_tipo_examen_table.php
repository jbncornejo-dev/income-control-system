<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_examen', function (Blueprint $table) {
            $table->id('id_tipo_examen');
            $table->string('nombre', 100);
            $table->string('codigo', 100);
            $table->boolean('activo')->default(true);

            $table->unique(['nombre'], 'uq_tipo_examen_nombre');
            $table->unique(['codigo'], 'uq_tipo_examen_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_examen');
    }
};
