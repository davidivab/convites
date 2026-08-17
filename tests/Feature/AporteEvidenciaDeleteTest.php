<?php

namespace Tests\Feature;

use App\Enums\EstadoAporte;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AporteEvidenciaDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function crearAporteConEvidencia(): array
    {
        Storage::fake(config('filesystems.upload'));

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);
        $item = IniciativaItem::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Cemento',
            'unidad' => 'bultos',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($aportante, 'sanctum')->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
            'items' => [['iniciativa_item_id' => $item->id, 'cantidad' => 2]],
        ])->assertCreated();

        $aporte = Aporte::query()->where('iniciativa_id', $iniciativa->id)->firstOrFail();

        $this->actingAs($creador, 'sanctum')->post("/api/aportes/{$aporte->id}/recepcion", [
            'recibido' => true,
            'evidencia' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        return [$creador, $aportante, $aporte->fresh()];
    }

    public function test_creador_elimina_evidencia_del_aporte(): void
    {
        [$creador, , $aporte] = $this->crearAporteConEvidencia();
        $disk = $aporte->evidencia_disk;
        $path = $aporte->evidencia_path;

        $this->actingAs($creador, 'sanctum')
            ->deleteJson("/api/aportes/{$aporte->id}/evidencia")
            ->assertOk()
            ->assertJsonPath('data.evidencia', null);

        $fresh = $aporte->fresh();
        $this->assertNull($fresh->evidencia_path);
        $this->assertNull($fresh->evidencia_disk);
        Storage::disk($disk)->assertMissing($path);

        // El estado de recepción (cumplido) no cambia, solo se quita el archivo.
        $this->assertSame(EstadoAporte::Cumplido, $fresh->estado);
    }

    public function test_aportante_no_puede_eliminar_evidencia_ajena(): void
    {
        [, $aportante, $aporte] = $this->crearAporteConEvidencia();

        $this->actingAs($aportante, 'sanctum')
            ->deleteJson("/api/aportes/{$aporte->id}/evidencia")
            ->assertForbidden();

        $this->assertNotNull($aporte->fresh()->evidencia_path);
    }
}
