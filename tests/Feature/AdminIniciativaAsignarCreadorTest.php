<?php

namespace Tests\Feature;

use App\Jobs\SendConviteAsignadoJob;
use App\Mail\ConviteAsignadoMail;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminIniciativaAsignarCreadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function municipioActivo(): Municipio
    {
        return Municipio::query()->where('activo', true)->firstOrFail();
    }

    public function test_admin_ve_datos_de_contacto_del_creador_en_show(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipio = $this->municipioActivo();
        $creador = User::factory()->create([
            'name' => 'Monica Creadora',
            'email' => 'monica@example.com',
            'celular' => '+57 300 111 2233',
            'municipio_id' => $municipio->id,
        ]);
        $creador->assignRole('member');

        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'slug' => 'monica-e-hijo-marlon',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/iniciativas/'.$ini->slug)
            ->assertOk()
            ->assertJsonPath('data.creador.id', $creador->id)
            ->assertJsonPath('data.creador.email', 'monica@example.com')
            ->assertJsonPath('data.creador.celular', '+57 300 111 2233')
            ->assertJsonPath('data.creador.name', 'Monica Creadora');
    }

    public function test_admin_asigna_creador_por_correo(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $municipio = $this->municipioActivo();
        $actual = User::factory()->create(['email' => 'viejo@example.com']);
        $actual->assignRole('member');
        $nuevo = User::factory()->create([
            'name' => 'Nuevo Dueño',
            'email' => 'nuevo@example.com',
            'celular' => '3009998877',
        ]);
        $nuevo->assignRole('member');

        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $actual->id,
            'municipio_id' => $municipio->id,
            'titulo' => 'Convite reasignado',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/iniciativas/'.$ini->slug.'/asignar-creador', [
                'email' => 'nuevo@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.creador.id', $nuevo->id)
            ->assertJsonPath('data.creador.email', 'nuevo@example.com');

        $this->assertDatabaseHas('iniciativas', [
            'id' => $ini->id,
            'user_id' => $nuevo->id,
        ]);

        Queue::assertPushed(SendConviteAsignadoJob::class, function (SendConviteAsignadoJob $job) use ($ini, $nuevo) {
            return $job->iniciativa->is($ini) && $job->nuevoCreador->is($nuevo);
        });
    }

    public function test_job_notifica_al_nuevo_creador_por_correo(): void
    {
        Mail::fake();

        $municipio = $this->municipioActivo();
        $nuevo = User::factory()->create([
            'name' => 'Laura Nueva',
            'email' => 'laura.nueva@example.com',
        ]);
        $nuevo->assignRole('member');
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $nuevo->id,
            'municipio_id' => $municipio->id,
            'titulo' => 'Convite de Laura',
            'slug' => 'convite-de-laura',
        ]);

        (new SendConviteAsignadoJob($ini, $nuevo))->handle();

        Mail::assertSent(ConviteAsignadoMail::class, function ($mail) use ($nuevo) {
            $rendered = $mail->render();

            return $mail->hasTo($nuevo->email)
                && str_contains($rendered, 'Convite de Laura')
                && str_contains($rendered, 'Te asignaron un convite');
        });
    }

    public function test_asignar_falla_si_correo_no_existe(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $this->municipioActivo()->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/iniciativas/'.$ini->slug.'/asignar-creador', [
                'email' => 'noexiste@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_asignar_falla_si_usuario_no_puede_crear_convites(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $creador = User::factory()->create();
        $creador->assignRole('member');
        $soloPro = User::factory()->create(['email' => 'solo-pro@example.com']);
        $soloPro->assignRole('profesional');

        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $this->municipioActivo()->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/iniciativas/'.$ini->slug.'/asignar-creador', [
                'email' => 'solo-pro@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_asignar_falla_si_ya_es_el_creador(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $creador = User::factory()->create(['email' => 'mismo@example.com']);
        $creador->assignRole('member');
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $this->municipioActivo()->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/iniciativas/'.$ini->slug.'/asignar-creador', [
                'email' => 'mismo@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_member_no_puede_asignar_creador(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $otro = User::factory()->create(['email' => 'otro@example.com']);
        $otro->assignRole('member');
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $this->municipioActivo()->id,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/admin/iniciativas/'.$ini->slug.'/asignar-creador', [
                'email' => 'otro@example.com',
            ])
            ->assertForbidden();
    }
}
