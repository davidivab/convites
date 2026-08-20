<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nueva categoría de iniciativa: Reactivación económica.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('categorias')
            ->where('slug', 'reactivacion-economica')
            ->exists();

        if ($exists) {
            return;
        }

        $maxOrden = (int) DB::table('categorias')->max('orden');

        DB::table('categorias')->insert([
            'slug' => 'reactivacion-economica',
            'nombre' => 'Reactivación económica',
            'descripcion' => null,
            'orden' => $maxOrden + 1,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categorias')
            ->where('slug', 'reactivacion-economica')
            ->delete();
    }
};
