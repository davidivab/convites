<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use App\Enums\EstadoAporte;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIniciativasReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_admin_lista_todas_y_ve_aportante_anonimo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $aportante = User::factory()->create(['name' => 'Secreto']);
        $aportante->assignRole('member');

        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Publicada,
        ]);
        $item = IniciativaItem::query()->create([
            'iniciativa_id' => $ini->id,
            'nombre' => 'Arena',
            'unidad' => 'bultos',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        Aporte::query()->create([
            'user_id' => $aportante->id,
            'iniciativa_id' => $ini->id,
            'estado' => EstadoAporte::Confirmado,
            'asiste_al_convite' => false,
            'anonimo' => true,
            'confirmado_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/iniciativas')
            ->assertOk()
            ->assertJsonFragment(['slug' => $ini->slug]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/iniciativas/'.$ini->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $ini->slug)
            ->assertJsonStructure(['data' => ['moderacion_historial', 'verificacion']]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/iniciativas/'.$ini->slug.'/aportes')
            ->assertOk()
            ->assertJsonPath('data.0.anonimo', true)
            ->assertJsonPath('data.0.aportante.name', 'Secreto');
    }

    public function test_member_no_accede_admin_iniciativas(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/iniciativas')
            ->assertForbidden();
    }
}
