<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 3 (avances-convite): schema shape for `iniciativa_avances` and
 * `iniciativa_avance_media` (M3/M4), per design's table shapes.
 */
class IniciativaAvanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_iniciativa_avances_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('iniciativa_avances'));
        $this->assertTrue(Schema::hasColumns('iniciativa_avances', [
            'id',
            'iniciativa_id',
            'iniciativa_item_id',
            'user_id',
            'slug',
            'titulo',
            'cuerpo',
            'porcentaje',
            'enlace_externo',
            'notificar_aportantes',
            'notificado_at',
            'publicado_at',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_iniciativa_avances_table_has_expected_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('iniciativa_avances'));

        $this->assertTrue($indexes->contains(
            fn (array $i) => $i['columns'] === ['iniciativa_id', 'slug'] && $i['unique']
        ), 'Expected unique index on (iniciativa_id, slug)');

        $this->assertTrue($indexes->contains(
            fn (array $i) => $i['columns'] === ['iniciativa_id', 'publicado_at']
        ), 'Expected index on (iniciativa_id, publicado_at)');

        $this->assertTrue($indexes->contains(
            fn (array $i) => $i['columns'] === ['iniciativa_item_id', 'publicado_at']
        ), 'Expected index on (iniciativa_item_id, publicado_at)');
    }

    public function test_iniciativa_avance_media_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('iniciativa_avance_media'));
        $this->assertTrue(Schema::hasColumns('iniciativa_avance_media', [
            'id',
            'iniciativa_avance_id',
            'path',
            'tipo',
            'orden',
            'ancho',
            'alto',
            'duracion_segundos',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_iniciativa_avance_media_table_has_expected_index(): void
    {
        $indexes = collect(Schema::getIndexes('iniciativa_avance_media'));

        $this->assertTrue($indexes->contains(
            fn (array $i) => $i['columns'] === ['iniciativa_avance_id', 'orden']
        ), 'Expected index on (iniciativa_avance_id, orden)');
    }
}
