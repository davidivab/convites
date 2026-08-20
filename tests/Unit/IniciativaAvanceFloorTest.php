<?php

namespace Tests\Unit;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\IniciativaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (avances-convite): `IniciativaAvance::floorPublicado()` — monotonic
 * percentage floor is the max PUBLISHED `porcentaje` for an item, excluding
 * drafts and (optionally) one avance id. Design D-C.
 */
class IniciativaAvanceFloorTest extends TestCase
{
    use RefreshDatabase;

    private function crearItem(): IniciativaItem
    {
        $iniciativa = Iniciativa::factory()->create();

        return IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Sacos de arroz',
            'unidad' => 'saco',
            'cantidad_meta' => 100,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);
    }

    public function test_floor_is_zero_when_item_has_no_published_avances(): void
    {
        $item = $this->crearItem();

        $floor = IniciativaAvance::floorPublicado($item->iniciativa_id, $item->id);

        $this->assertSame(0, $floor);
    }

    public function test_floor_excludes_draft_avances(): void
    {
        $item = $this->crearItem();

        IniciativaAvance::query()->create([
            'iniciativa_id' => $item->iniciativa_id,
            'iniciativa_item_id' => $item->id,
            'user_id' => Iniciativa::find($item->iniciativa_id)->user_id,
            'slug' => 'borrador-avance',
            'titulo' => 'Borrador',
            'porcentaje' => 90,
            'publicado_at' => null,
        ]);

        $floor = IniciativaAvance::floorPublicado($item->iniciativa_id, $item->id);

        $this->assertSame(0, $floor);
    }

    public function test_floor_is_max_porcentaje_among_published_avances(): void
    {
        $item = $this->crearItem();
        $userId = Iniciativa::find($item->iniciativa_id)->user_id;

        IniciativaAvance::query()->create([
            'iniciativa_id' => $item->iniciativa_id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $userId,
            'slug' => 'avance-1',
            'titulo' => 'Avance 1',
            'porcentaje' => 40,
            'publicado_at' => now(),
        ]);

        IniciativaAvance::query()->create([
            'iniciativa_id' => $item->iniciativa_id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $userId,
            'slug' => 'avance-2',
            'titulo' => 'Avance 2',
            'porcentaje' => 25,
            'publicado_at' => now(),
        ]);

        $floor = IniciativaAvance::floorPublicado($item->iniciativa_id, $item->id);

        $this->assertSame(40, $floor);
    }

    public function test_floor_excludes_except_id(): void
    {
        $item = $this->crearItem();
        $userId = Iniciativa::find($item->iniciativa_id)->user_id;

        $maxHolder = IniciativaAvance::query()->create([
            'iniciativa_id' => $item->iniciativa_id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $userId,
            'slug' => 'avance-max',
            'titulo' => 'Avance max',
            'porcentaje' => 40,
            'publicado_at' => now(),
        ]);

        IniciativaAvance::query()->create([
            'iniciativa_id' => $item->iniciativa_id,
            'iniciativa_item_id' => $item->id,
            'user_id' => $userId,
            'slug' => 'avance-menor',
            'titulo' => 'Avance menor',
            'porcentaje' => 20,
            'publicado_at' => now(),
        ]);

        $floor = IniciativaAvance::floorPublicado($item->iniciativa_id, $item->id, $maxHolder->id);

        $this->assertSame(20, $floor);
    }
}
