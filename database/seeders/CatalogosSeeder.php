<?php

namespace Database\Seeders;

use App\Enums\TipoHabilidad;
use App\Models\Categoria;
use App\Models\Disponibilidad;
use App\Models\Habilidad;
use App\Models\Zona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogos base alineados al front v0 (`lib/data.ts`).
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedZonas();
        $this->seedCategorias();
        $this->seedHabilidades();
        $this->seedDisponibilidades();
    }

    private function seedZonas(): void
    {
        $zonas = [
            ['nombre' => 'Dosquebradas', 'municipio' => 'Dosquebradas'],
            ['nombre' => 'Pereira — Villa Santana', 'municipio' => 'Pereira'],
            ['nombre' => 'Pereira — Centro', 'municipio' => 'Pereira'],
            ['nombre' => 'Santa Rosa de Cabal', 'municipio' => 'Santa Rosa de Cabal'],
            ['nombre' => 'Marsella', 'municipio' => 'Marsella'],
            ['nombre' => 'La Virginia', 'municipio' => 'La Virginia'],
        ];

        foreach ($zonas as $i => $zona) {
            Zona::query()->updateOrCreate(
                ['slug' => Str::slug($zona['nombre'])],
                [
                    'nombre' => $zona['nombre'],
                    'municipio' => $zona['municipio'],
                    'orden' => $i + 1,
                    'activo' => true,
                ],
            );
        }
    }

    private function seedCategorias(): void
    {
        $categorias = [
            ['slug' => 'vivienda', 'nombre' => 'Vivienda'],
            ['slug' => 'comunitario', 'nombre' => 'Espacio comunitario'],
            ['slug' => 'educacion', 'nombre' => 'Educación'],
            ['slug' => 'alimentacion', 'nombre' => 'Alimentación'],
            ['slug' => 'herramientas', 'nombre' => 'Herramientas'],
            ['slug' => 'reactivacion-economica', 'nombre' => 'Reactivación económica'],
        ];

        foreach ($categorias as $i => $categoria) {
            Categoria::query()->updateOrCreate(
                ['slug' => $categoria['slug']],
                [
                    'nombre' => $categoria['nombre'],
                    'orden' => $i + 1,
                    'activo' => true,
                ],
            );
        }
    }

    private function seedHabilidades(): void
    {
        $manuales = [
            'Albañilería y construcción',
            'Carpintería',
            'Plomería',
            'Electricidad',
            'Pintura',
            'Techado y tejas',
            'Soldadura',
            'Cocina para grupos',
            'Manejo de herramientas',
            'Cargue y trabajo de fuerza',
            'Jardinería y limpieza',
            'Conducción / transporte',
        ];

        $conocimiento = [
            'Primeros auxilios / salud',
            'Enseñanza y refuerzo escolar',
            'Trámites y papeleo',
            'Diseño y comunicación',
            'Contabilidad / manejo de recursos',
            'Coordinación y logística',
            'Traducción / lenguas indígenas',
            'Acompañamiento psicosocial',
        ];

        foreach ($manuales as $i => $nombre) {
            Habilidad::query()->updateOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'nombre' => $nombre,
                    'tipo' => TipoHabilidad::Manual,
                    'orden' => $i + 1,
                    'activo' => true,
                ],
            );
        }

        foreach ($conocimiento as $i => $nombre) {
            Habilidad::query()->updateOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'nombre' => $nombre,
                    'tipo' => TipoHabilidad::Conocimiento,
                    'orden' => $i + 1,
                    'activo' => true,
                ],
            );
        }
    }

    private function seedDisponibilidades(): void
    {
        $items = [
            'Entre semana en la mañana',
            'Entre semana en la tarde',
            'Fines de semana',
            'Solo en emergencias',
            'Disponible para viajar a otras veredas',
        ];

        foreach ($items as $i => $nombre) {
            Disponibilidad::query()->updateOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'nombre' => $nombre,
                    'orden' => $i + 1,
                    'activo' => true,
                ],
            );
        }
    }
}
