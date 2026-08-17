<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\Municipio;
use App\Models\User;
use App\Notifications\IniciativaPendienteModeracionNotification;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModeratorNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    public function test_solo_moderador_del_municipio_recibe_aviso_de_revision(): void
    {
        Notification::fake();

        $mine = Municipio::query()->where('activo', true)->orderBy('id')->firstOrFail();
        $other = Municipio::query()->where('activo', true)->where('id', '!=', $mine->id)->firstOrFail();

        $modMine = User::factory()->create();
        $modMine->assignRole('moderator');
        $modMine->municipiosAsignados()->sync([$mine->id]);

        $modOther = User::factory()->create();
        $modOther->assignRole('moderator');
        $modOther->municipiosAsignados()->sync([$other->id]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creador = User::factory()->create();
        $creador->assignRole('member');

        $ini = Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $mine->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Borrador,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $ini->id,
            'nombre' => 'Arena',
            'unidad' => 'bultos',
            'cantidad_meta' => 5,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);

        $this->actingAs($creador, 'sanctum')
            ->postJson('/api/iniciativas/'.$ini->id.'/enviar-revision')
            ->assertOk();

        Notification::assertSentTo($modMine, IniciativaPendienteModeracionNotification::class);
        Notification::assertSentTo($admin, IniciativaPendienteModeracionNotification::class);
        Notification::assertNotSentTo($modOther, IniciativaPendienteModeracionNotification::class);
    }

    public function test_inbox_lista_notificaciones_propias(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $user->notify(new IniciativaPendienteModeracionNotification(
            Iniciativa::factory()->create([
                'municipio_id' => Municipio::query()->where('activo', true)->value('id'),
                'zona_id' => null,
                'estado' => EstadoIniciativa::EnRevision,
            ]),
        ));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.data.tipo', 'iniciativa_pendiente_moderacion');
    }

    public function test_unread_filter_solo_devuelve_no_leidas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $ini = Iniciativa::factory()->create([
            'municipio_id' => Municipio::query()->where('activo', true)->value('id'),
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);

        $user->notify(new IniciativaPendienteModeracionNotification($ini));
        $user->notify(new IniciativaPendienteModeracionNotification($ini));
        $leida = $user->notifications()->first();
        $leida->markAsRead();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_mark_read_marca_una_notificacion_como_leida(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $ini = Iniciativa::factory()->create([
            'municipio_id' => Municipio::query()->where('activo', true)->value('id'),
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);
        $user->notify(new IniciativaPendienteModeracionNotification($ini));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId)
            ->assertJsonStructure(['data' => ['id', 'read_at']]);

        $this->assertNotNull($user->notifications()->whereKey($notificationId)->first()->read_at);
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_mark_read_de_notificacion_ajena_da_404(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('moderator');
        $other = User::factory()->create();
        $other->assignRole('moderator');

        $ini = Iniciativa::factory()->create([
            'municipio_id' => Municipio::query()->where('activo', true)->value('id'),
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);
        $owner->notify(new IniciativaPendienteModeracionNotification($ini));
        $notificationId = $owner->notifications()->first()->id;

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/notifications/{$notificationId}/read")
            ->assertNotFound();
    }

    public function test_mark_all_read_marca_todas_como_leidas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $ini = Iniciativa::factory()->create([
            'municipio_id' => Municipio::query()->where('activo', true)->value('id'),
            'zona_id' => null,
            'estado' => EstadoIniciativa::EnRevision,
        ]);
        $user->notify(new IniciativaPendienteModeracionNotification($ini));
        $user->notify(new IniciativaPendienteModeracionNotification($ini));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }
}
