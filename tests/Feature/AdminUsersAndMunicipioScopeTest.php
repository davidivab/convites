<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersAndMunicipioScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_admin_crea_moderador_con_municipios(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipios = Municipio::query()->where('activo', true)->take(2)->pluck('id')->all();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Mod Pereira',
                'email' => 'mod.pereira@convites.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'moderator',
                'municipio_ids' => $municipios,
            ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'moderator')
            ->assertJsonCount(2, 'data.municipios');

        $created = User::query()->where('email', 'mod.pereira@convites.test')->firstOrFail();
        $this->assertTrue($created->hasRole('moderator'));
        $this->assertEqualsCanonicalizing($municipios, $created->assignedMunicipioIds());
    }

    public function test_admin_crea_voluntario(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $municipioId = Municipio::query()->where('activo', true)->value('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Vol Dosquebradas',
                'email' => 'vol@convites.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'voluntario',
                'municipio_ids' => [$municipioId],
            ])
            ->assertCreated()
            ->assertJsonPath('data.roles.0', 'voluntario');
    }

    public function test_member_no_puede_crear_usuarios_admin(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $municipioId = Municipio::query()->where('activo', true)->value('id');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Hack',
                'email' => 'hack@convites.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'moderator',
                'municipio_ids' => [$municipioId],
            ])
            ->assertForbidden();
    }

    public function test_moderador_solo_ve_y_aprueba_sus_municipios(): void
    {
        $mod = User::factory()->create();
        $mod->assignRole('moderator');

        $mine = Municipio::query()->where('activo', true)->orderBy('id')->firstOrFail();
        $other = Municipio::query()->where('activo', true)->where('id', '!=', $mine->id)->firstOrFail();
        $mod->municipiosAsignados()->sync([$mine->id]);

        $iniMine = Iniciativa::factory()->create([
            'municipio_id' => $mine->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
            'enviada_revision_at' => now(),
        ]);
        $iniOther = Iniciativa::factory()->create([
            'municipio_id' => $other->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
            'enviada_revision_at' => now(),
        ]);

        $list = $this->actingAs($mod, 'sanctum')
            ->getJson('/api/moderacion/iniciativas')
            ->assertOk();

        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($iniMine->id, $ids);
        $this->assertNotContains($iniOther->id, $ids);

        $this->actingAs($mod, 'sanctum')
            ->postJson("/api/moderacion/iniciativas/{$iniMine->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'publicada');

        $this->actingAs($mod, 'sanctum')
            ->postJson("/api/moderacion/iniciativas/{$iniOther->id}/aprobar")
            ->assertForbidden();
    }

    public function test_admin_aprueba_cualquier_municipio(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $ini = Iniciativa::factory()->create([
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
            'enviada_revision_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/moderacion/iniciativas/{$ini->id}/aprobar")
            ->assertOk();
    }
}
