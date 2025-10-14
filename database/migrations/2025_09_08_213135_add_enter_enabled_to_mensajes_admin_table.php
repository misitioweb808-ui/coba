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
        Schema::table('mensajes_admin', function (Blueprint $table) {
            $table->boolean('enter_enabled')->default(true)->after('tipo_mensaje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes_admin', function (Blueprint $table) {
            $table->dropColumn('enter_enabled');
        });
    }
};
