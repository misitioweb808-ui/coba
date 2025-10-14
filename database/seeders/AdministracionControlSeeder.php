<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministracionControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('administracion_control')->insert([
            'usuario' => 'admin',
            'clave' => bcrypt('xd123123'),
            'permisos' => json_encode([
                'ver_registros' => true,
                'editar_registros' => true,
                'eliminar_registros' => true
            ]),
            'is_admin' => true,
            'is_mod' => false,
            'fecha_creacion' => now(),
        ]);
    }
}
