<?php

use App\Models\Departamento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag de emergencia en catálogo geo: los selects del front listan primero
 * los departamentos/municipios con emergencia=true (luego alfabético).
 *
 * Solo UPDATE de flags — sin truncate/seed destructivo.
 */
return new class extends Migration
{
    private const DEPTOS_EMERGENCIA = [
        'Risaralda',
        'Chocó',
        'Valle del Cauca',
    ];

    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->boolean('emergencia')->default(false)->after('activo');
        });

        Schema::table('municipios', function (Blueprint $table) {
            $table->boolean('emergencia')->default(false)->after('activo');
        });

        DB::table('departamentos')
            ->whereIn('nombre', self::DEPTOS_EMERGENCIA)
            ->update(['emergencia' => true]);

        $deptIds = Departamento::query()
            ->whereIn('nombre', self::DEPTOS_EMERGENCIA)
            ->pluck('id');

        if ($deptIds->isNotEmpty()) {
            DB::table('municipios')
                ->whereIn('departamento_id', $deptIds)
                ->update(['emergencia' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {
            $table->dropColumn('emergencia');
        });

        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('emergencia');
        });
    }
};
