<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambiente', function (Blueprint $table) {
            $table->id('id_ambiente');
            $table->string('nombre_ambiente', 100)->unique();
            $table->integer('capacidad')->unsigned();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambiente');
    }
};
