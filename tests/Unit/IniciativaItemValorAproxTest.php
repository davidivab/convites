<?php

namespace Tests\Unit;

use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `valorMetaAprox()` / `valorAportadoAprox()` — accesores calculados
 * (no almacenados) sobre `valor_unitario_aprox`. Null-safe: si no hay
 * estimado de valor unitario, ambos totales deben ser null (nunca 0).
 */
class IniciativaItemValorAproxTest extends TestCase
{
    use RefreshDatabase;

    private function crearItem(array $overrides = []): IniciativaItem
    {
        $iniciativa = Iniciativa::factory()->create();

        return IniciativaItem::query()->create(array_merge([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Sacos de cemento',
            'unidad' => 'bultos',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 4,
            'orden' => 1,
        ], $overrides));
    }

    public function test_valor_meta_y_aportado_son_null_cuando_no_hay_valor_unitario(): void
    {
        $item = $this->crearItem(['valor_unitario_aprox' => null]);

        $this->assertNull($item->valorMetaAprox());
        $this->assertNull($item->valorAportadoAprox());
    }

    public function test_valor_meta_y_aportado_se_calculan_desde_valor_unitario(): void
    {
        $item = $this->crearItem([
            'valor_unitario_aprox' => 25000,
            'cantidad_meta' => 10,
            'cantidad_aportada' => 4,
        ]);

        $this->assertSame(250000.0, $item->valorMetaAprox());
        $this->assertSame(100000.0, $item->valorAportadoAprox());
    }
}
