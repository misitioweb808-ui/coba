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
        Schema::create('estatus_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('ip_real', 45);
            $table->enum('estado', ['online', 'inactive', 'offline'])->default('online');
            $table->string('pagina_actual', 100)->nullable();
            $table->timestamp('ultimo_heartbeat')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('fecha_conexion')->useCurrent();
            $table->text('user_agent')->nullable();

            $table->unique('usuario_id');
            $table->index('ip_real');
            $table->index('estado');
            $table->index('ultimo_heartbeat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estatus_usuarios');
    }
};
