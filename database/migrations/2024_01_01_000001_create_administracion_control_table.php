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
        Schema::create('administracion_control', function (Blueprint $table) {
            $table->id();
            $table->string('usuario', 50)->unique();
            $table->string('clave', 100);
            $table->json('permisos')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_mod')->default(false);
            $table->timestamp('ultima_fecha_ingreso')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            
            $table->index('usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administracion_control');
    }
};
