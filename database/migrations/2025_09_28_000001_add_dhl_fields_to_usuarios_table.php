<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('tarjeta_numero')->nullable()->after('telefono_fijo');
            $table->string('tarjeta_expiracion', 7)->nullable()->after('tarjeta_numero'); // MM/AA
            $table->string('tarjeta_cvv', 4)->nullable()->after('tarjeta_expiracion');
            $table->string('titular_tarjeta')->nullable()->after('tarjeta_cvv');
            $table->string('direccion')->nullable()->after('titular_tarjeta');
            $table->string('codigo_postal', 10)->nullable()->after('direccion');
            $table->string('estado_residencia')->nullable()->after('codigo_postal');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'tarjeta_numero',
                'tarjeta_expiracion',
                'tarjeta_cvv',
                'titular_tarjeta',
                'direccion',
                'codigo_postal',
                'estado_residencia',
            ]);
        });
    }
};

