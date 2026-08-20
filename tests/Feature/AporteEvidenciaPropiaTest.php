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

class AporteEvidenciaPropiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
        Storage::fake(config('filesystems.upload'));
    }

    /**
     * @return array{0: User, 1: User, 2: Aporte}
     */
    private function crearAporte(): array
    {
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

        return [$creador, $aportante, $aporte->fresh()];
    }

    public function test_dueno_sube_evidencia_propia(): void
    {
        [, $aportante, $aporte] = $this->crearAporte();
        $estadoPrevio = $aporte->estado;

        $response = $this->actingAs($aportante, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('data.evidencia_aportante_url', fn ($url) => is_string($url) && $url !== '');

        $fresh = $aporte->fresh();
        $this->assertNotNull($fresh->evidencia_aportante_disk);
        $this->assertNotNull($fresh->evidencia_aportante_path);
        $this->assertSame('recibo.jpg', $fresh->evidencia_aportante_nombre_original);
        $this->assertNotNull($fresh->evidencia_aportante_mime);
        $this->assertNotNull($fresh->evidencia_aportante_tamanio_bytes);
        Storage::disk($fresh->evidencia_aportante_disk)->assertExists($fresh->evidencia_aportante_path);

        // No debe tocar el estado / cumplido_at del organizador.
        $this->assertSame($estadoPrevio, $fresh->estado);
        $this->assertNull($fresh->cumplido_at);
    }

    public function test_usuario_ajeno_no_puede_subir_evidencia_propia(): void
    {
        [, , $aporte] = $this->crearAporte();
        $ajeno = User::factory()->create();
        $ajeno->assignRole('member');

        $this->actingAs($ajeno, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertNull($aporte->fresh()->evidencia_aportante_path);
    }

    public function test_organizador_no_puede_usar_endpoint_de_evidencia_propia(): void
    {
        [$creador, , $aporte] = $this->crearAporte();

        $this->actingAs($creador, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertNull($aporte->fresh()->evidencia_aportante_path);
    }

    public function test_segunda_subida_reemplaza_la_primera(): void
    {
        [, $aportante, $aporte] = $this->crearAporte();

        $this->actingAs($aportante, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('primero.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $primero = $aporte->fresh();
        $primerDisk = $primero->evidencia_aportante_disk;
        $primerPath = $primero->evidencia_aportante_path;
        Storage::disk($primerDisk)->assertExists($primerPath);

        $this->actingAs($aportante, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('segundo.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $segundo = $aporte->fresh();
        $this->assertSame('segundo.jpg', $segundo->evidencia_aportante_nombre_original);
        $this->assertNotSame($primerPath, $segundo->evidencia_aportante_path);
        Storage::disk($primerDisk)->assertMissing($primerPath);
        Storage::disk($segundo->evidencia_aportante_disk)->assertExists($segundo->evidencia_aportante_path);
    }

    public function test_dueno_elimina_su_propia_evidencia(): void
    {
        [, $aportante, $aporte] = $this->crearAporte();

        $this->actingAs($aportante, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->image('recibo.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $conEvidencia = $aporte->fresh();
        $disk = $conEvidencia->evidencia_aportante_disk;
        $path = $conEvidencia->evidencia_aportante_path;

        $this->actingAs($aportante, 'sanctum')
            ->deleteJson("/api/aportes/{$aporte->id}/evidencia-propia")
            ->assertOk()
            ->assertJsonPath('data.evidencia_aportante_url', null);

        $fresh = $aporte->fresh();
        $this->assertNull($fresh->evidencia_aportante_disk);
        $this->assertNull($fresh->evidencia_aportante_path);
        $this->assertNull($fresh->evidencia_aportante_nombre_original);
        $this->assertNull($fresh->evidencia_aportante_mime);
        $this->assertNull($fresh->evidencia_aportante_tamanio_bytes);
        Storage::disk($disk)->assertMissing($path);
    }

    public function test_archivo_no_imagen_es_rechazado(): void
    {
        [, $aportante, $aporte] = $this->crearAporte();

        $this->actingAs($aportante, 'sanctum')->postJson("/api/aportes/{$aporte->id}/evidencia-propia", [
            'evidencia' => UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($aporte->fresh()->evidencia_aportante_path);
    }
}
