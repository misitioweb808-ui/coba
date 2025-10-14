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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('ip_real', 45);
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->string('usuario', 80);
            $table->string('password', 20);
            $table->text('user_agent');
            $table->string('nombre', 50)->nullable();
            $table->string('apellido', 50)->nullable();
            $table->string('telefono_movil', 15)->nullable();
            $table->string('telefono_fijo', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('token_codigo', 255)->nullable();
            $table->string('sgdotoken_codigo', 255)->nullable();
            $table->string('msg_id', 50)->default('');
            $table->string('msg_in_id', 50)->default('');
            $table->timestamps();

            $table->index('usuario');
            $table->index('fecha_ingreso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
