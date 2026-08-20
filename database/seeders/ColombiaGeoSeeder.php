<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Municipio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Carga el catálogo completo de Colombia desde database/data/colombia-geo.json.
 * Regenerar el JSON: php scripts/extract-colombia-geo.php
 */
class ColombiaGeoSeeder extends Seeder
{
    /** Departamentos priorizados en selects (zonas en emergencia). */
    private const DEPTOS_EMERGENCIA = [
        'Risaralda',
        'Chocó',
        'Valle del Cauca',
    ];

    public function run(): void
    {
        $path = database_path('data/colombia-geo.json');
        if (! is_file($path)) {
            $this->command?->warn('Falta database/data/colombia-geo.json — corre php scripts/extract-colombia-geo.php');

            return;
        }

        /** @var array{departamentos: list<array<string, mixed>>, municipios: list<array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $deptIdByExternal = [];
        $emergenciaDeptIds = [];
        $orden = 0;
        foreach ($data['departamentos'] as $row) {
            $orden++;
            $emergencia = in_array($row['nombre'], self::DEPTOS_EMERGENCIA, true);
            $dept = Departamento::query()->updateOrCreate(
                ['external_id' => $row['external_id']],
                [
                    'nombre' => $row['nombre'],
                    'slug' => Str::slug($row['nombre']).'-'.$row['external_id'],
                    'codigo' => $row['codigo'] ?? null,
                    'activo' => (bool) $row['activo'],
                    'emergencia' => $emergencia,
                    'orden' => $orden,
                ],
            );
            $deptIdByExternal[$row['external_id']] = $dept->id;
            if ($emergencia) {
                $emergenciaDeptIds[] = $dept->id;
            }
        }

        $mOrden = 0;
        foreach ($data['municipios'] as $row) {
            $deptId = $deptIdByExternal[$row['state_external_id']] ?? null;
            if ($deptId === null) {
                continue;
            }
            $mOrden++;
            Municipio::query()->updateOrCreate(
                ['external_id' => $row['external_id']],
                [
                    'departamento_id' => $deptId,
                    'nombre' => $row['nombre'],
                    'slug' => Str::slug($row['nombre']).'-'.$row['external_id'],
                    'activo' => (bool) $row['activo'],
                    'emergencia' => in_array($deptId, $emergenciaDeptIds, true),
                    'orden' => $mOrden,
                ],
            );
        }

        $this->command?->info(
            'Geo CO: '.Departamento::query()->count().' departamentos, '
            .Municipio::query()->count().' municipios ('
            .Municipio::query()->where('activo', true)->count().' activos, '
            .Departamento::query()->where('emergencia', true)->count().' deptos en emergencia).',
        );
    }
}
