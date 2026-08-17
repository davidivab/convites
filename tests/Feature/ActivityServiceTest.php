<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use App\Enums\EstadoAporte;
use App\Enums\EstadoIniciativa;
use App\Services\ActivityService;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_create_activity_persists_and_logs(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user, 'sanctum');

        $activity = app(ActivityService::class)->createActivity([
            'message' => 'Prueba activity',
            'status_text' => 'ok',
            'status' => 'success',
            'color' => Activity::COLOR_SUCCESS,
            'data' => ['foo' => 'bar'],
        ]);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'message' => 'Prueba activity',
            'userable_id' => $user->id,
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message) {
            return $message === 'Prueba activity';
        })->once();
    }

    public function test_moderacion_y_aporte_registran_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creador = User::factory()->create();
        $creador->assignRole('member');

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $ini->id,
            'nombre' => 'Arena',
            'unidad' => 'bultos',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/moderacion/iniciativas/'.$ini->id.'/aprobar', ['nota' => 'ok'])
            ->assertOk();

        $this->assertDatabaseHas('activities', [
            'modelable_type' => Iniciativa::class,
            'modelable_id' => $ini->id,
            'status' => 'aprobar',
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');
        $item = $ini->items()->firstOrFail();

        $this->actingAs($aportante, 'sanctum')
            ->postJson('/api/iniciativas/'.$ini->id.'/aportes', [
                'items' => [['iniciativa_item_id' => $item->id, 'cantidad' => 1]],
            ])
            ->assertCreated();

        $aporte = Aporte::query()->where('user_id', $aportante->id)->firstOrFail();
        $this->assertSame(EstadoAporte::Confirmado, $aporte->estado);

        $this->assertDatabaseHas('activities', [
            'modelable_type' => Aporte::class,
            'modelable_id' => $aporte->id,
            'status' => 'confirmado',
        ]);
    }
}
