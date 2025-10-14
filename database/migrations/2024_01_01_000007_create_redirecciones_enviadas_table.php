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
        Schema::create('redirecciones_enviadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('administracion_control')->onDelete('cascade');
            $table->string('url_destino', 500);
            $table->enum('tipo_redireccion', ['url_personalizada', 'index'])->default('index');
            $table->text('mensaje_confirmacion')->nullable();
            $table->enum('estado', ['pendiente', 'visto', 'cerrado'])->default('pendiente');
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('fecha_visto')->nullable();
            $table->timestamp('fecha_cerrado')->nullable();
            $table->string('ip_usuario', 45);
            
            $table->index('usuario_id');
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirecciones_enviadas');
    }
};
