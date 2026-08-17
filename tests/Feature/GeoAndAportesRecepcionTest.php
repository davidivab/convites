<?php

namespace Tests\Feature;

use App\Enums\EstadoAporte;
use App\Enums\EstadoIniciativa;
use App\Models\Aporte;
use App\Models\Departamento;
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

class GeoAndAportesRecepcionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_catalogos_departamentos_y_municipios_activos(): void
    {
        $this->getJson('/api/catalogos/departamentos')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $dept = Departamento::query()->where('activo', true)->firstOrFail();

        $this->getJson('/api/catalogos/municipios?departamento_id='.$dept->id)
            ->assertOk()
            ->assertJsonFragment(['departamento_id' => $dept->id]);

        $this->assertGreaterThanOrEqual(33, Departamento::query()->count());
        $this->assertGreaterThanOrEqual(1000, Municipio::query()->count());
        $this->assertSame(3, Departamento::query()->where('activo', true)->count());
    }

    public function test_creador_lista_aportantes_anonimo_y_marca_recepcion_con_evidencia(): void
    {
        Storage::fake(config('filesystems.upload'));

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $aportante = User::factory()->create(['name' => 'Vecino Visible']);
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

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'anonimo' => true,
                'asiste_al_convite' => false,
                'items' => [
                    ['iniciativa_item_id' => $item->id, 'cantidad' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.anonimo', true);

        $this->app['auth']->forgetGuards();

        $list = $this->actingAs($creador, 'sanctum')
            ->getJson("/api/iniciativas/{$iniciativa->id}/aportantes")
            ->assertOk();

        $list->assertJsonPath('data.0.anonimo', true)
            ->assertJsonPath('data.0.aportante.name', 'Aporte anónimo');

        $aporteId = Aporte::query()->where('iniciativa_id', $iniciativa->id)->value('id');
        $this->assertNotNull($aporteId);

        $file = UploadedFile::fake()->image('recibo.jpg', 400, 300);

        $this->actingAs($creador, 'sanctum')
            ->post("/api/aportes/{$aporteId}/recepcion", [
                'recibido' => true,
                'evidencia' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoAporte::Cumplido->value)
            ->assertJsonPath('data.evidencia.nombre', 'recibo.jpg');

        $aporte = Aporte::query()->findOrFail($aporteId);
        $this->assertNotNull($aporte->evidencia_path);
        Storage::disk($aporte->evidencia_disk)->assertExists($aporte->evidencia_path);
    }

    public function test_otro_usuario_no_lista_aportantes(): void
    {
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $otro = User::factory()->create();
        $otro->assignRole('member');

        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'estado' => EstadoIniciativa::Publicada,
        ]);

        $this->actingAs($otro, 'sanctum')
            ->getJson("/api/iniciativas/{$iniciativa->id}/aportantes")
            ->assertForbidden();
    }
}
