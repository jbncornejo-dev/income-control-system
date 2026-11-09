<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examen_qr', function (Blueprint $table) {
            $table->id('id_examen_qr');
            $table->foreignId('id_habilitacion')
                ->constrained('habilitacion', 'id_habilitacion')
                ->cascadeOnDelete();
            $table->foreignId('id_estudiante')
                ->constrained('estudiante', 'id_estudiante')
                ->cascadeOnDelete();
            $table->foreignId('id_examen')
                ->constrained('examen', 'id_examen')
                ->cascadeOnDelete();
            $table->string('token', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['id_examen', 'id_estudiante']);
            $table->index('id_habilitacion');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examen_qr');
    }
};
