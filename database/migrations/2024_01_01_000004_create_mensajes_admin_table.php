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
        Schema::create('mensajes_admin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('administracion_control')->onDelete('cascade');
            $table->text('mensaje');
            $table->enum('tipo_mensaje', ['con_input', 'sin_input'])->default('sin_input');
            $table->text('respuesta_usuario')->nullable();
            $table->enum('estado', ['pendiente', 'leido', 'respondido', 'cancelado'])->default('pendiente');
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('fecha_leido')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->string('ip_usuario', 45);
            
            $table->index('usuario_id');
            $table->index('admin_id');
            $table->index('estado');
            $table->index('tipo_mensaje');
            $table->index('ip_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes_admin');
    }
};
