<?php

namespace Tests\Feature;

use App\Jobs\SendProveedorInstruccionesJob;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\IniciativaProveedor;
use App\Models\IniciativaPuntoAcopio;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AportePuntoAcopioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
    }

    private function crearIniciativaConPunto(): array
    {
        $municipios = Municipio::query()->where('activo', true)->take(2)->pluck('id');
        $creador = User::factory()->create();
        $creador->assignRole('member');

        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipios[0],
            'zona_id' => null,
        ]);

        $punto = IniciativaPuntoAcopio::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'municipio_id' => $municipios[1],
            'nombre' => 'Punto Bogotá',
            'direccion' => 'Calle 1 # 2-3',
            'orden' => 1,
        ]);

        return [$iniciativa, $punto];
    }

    private function crearIniciativaConProveedor(): array
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');

        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $proveedor = IniciativaProveedor::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'nombre' => 'Ferretería El Martillo',
            'direccion' => 'Av. Siempre Viva 123',
            'ciudad' => 'Bogotá',
            'correo' => 'contacto@elmartillo.com',
            'celular' => '3001234567',
            'instrucciones_pago' => 'Transferencia a la cuenta 123-456.',
        ]);

        return [$iniciativa, $proveedor];
    }

    public function test_aporta_con_punto_de_acopio_valido(): void
    {
        [$iniciativa, $punto] = $this->crearIniciativaConPunto();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'punto_acopio_id' => $punto->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.punto_acopio.id', $punto->id)
            ->assertJsonPath('data.punto_acopio.nombre', 'Punto Bogotá');

        $this->assertSame($punto->id, $iniciativa->aportes()->firstOrFail()->punto_acopio_id);
    }

    public function test_rechaza_punto_de_acopio_de_otra_iniciativa(): void
    {
        [$iniciativa] = $this->crearIniciativaConPunto();
        [, $puntoAjeno] = $this->crearIniciativaConPunto();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'punto_acopio_id' => $puntoAjeno->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['punto_acopio_id']);
    }

    public function test_aporte_sin_punto_de_acopio_sigue_funcionando(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.punto_acopio', null);
    }

    public function test_aporta_con_fecha_entrega_valida_dentro_de_rango(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'fecha_limite_aportes' => now()->addDays(10)->toDateString(),
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $fechaEntrega = now()->addDays(5)->toDateString();

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'fecha_entrega' => $fechaEntrega,
            ])
            ->assertCreated()
            ->assertJsonPath('data.fecha_entrega', $fechaEntrega);

        $this->assertSame(
            $fechaEntrega,
            $iniciativa->aportes()->firstOrFail()->fecha_entrega->toDateString(),
        );
    }

    public function test_rechaza_fecha_entrega_anterior_a_hoy(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'fecha_entrega' => now()->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_entrega'])
            ->assertJsonFragment([
                'fecha_entrega' => ['La fecha de entrega no puede ser antes de hoy.'],
            ]);
    }

    public function test_rechaza_fecha_entrega_posterior_a_fecha_limite_aportes(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'fecha_limite_aportes' => now()->addDays(5)->toDateString(),
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'fecha_entrega' => now()->addDays(10)->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_entrega'])
            ->assertJsonFragment([
                'fecha_entrega' => ['La fecha de entrega no puede ser después de la fecha límite de aportes de esta iniciativa.'],
            ]);
    }

    public function test_aporte_sin_fecha_entrega_sigue_funcionando(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.fecha_entrega', null);
    }

    public function test_aporta_con_proveedor_valido(): void
    {
        [$iniciativa, $proveedor] = $this->crearIniciativaConProveedor();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'proveedor_id' => $proveedor->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.proveedor.id', $proveedor->id)
            ->assertJsonPath('data.proveedor.nombre', 'Ferretería El Martillo')
            ->assertJsonPath('data.proveedor.instrucciones_pago', 'Transferencia a la cuenta 123-456.');

        $this->assertSame($proveedor->id, $iniciativa->aportes()->firstOrFail()->proveedor_id);
    }

    public function test_rechaza_proveedor_de_otra_iniciativa(): void
    {
        [$iniciativa] = $this->crearIniciativaConProveedor();
        [, $proveedorAjeno] = $this->crearIniciativaConProveedor();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'proveedor_id' => $proveedorAjeno->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proveedor_id']);
    }

    public function test_aporte_sin_proveedor_sigue_funcionando(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.proveedor', null);
    }

    public function test_confirmar_aporte_con_proveedor_encola_job_de_instrucciones(): void
    {
        Queue::fake();

        [$iniciativa, $proveedor] = $this->crearIniciativaConProveedor();

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
                'proveedor_id' => $proveedor->id,
            ])
            ->assertCreated();

        Queue::assertPushed(SendProveedorInstruccionesJob::class);
    }

    public function test_confirmar_aporte_sin_proveedor_no_encola_job_de_instrucciones(): void
    {
        Queue::fake();

        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $creador->assignRole('member');
        $iniciativa = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $aportante = User::factory()->create();
        $aportante->assignRole('member');

        $this->actingAs($aportante, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/aportes", [
                'asiste_al_convite' => true,
            ])
            ->assertCreated();

        Queue::assertNotPushed(SendProveedorInstruccionesJob::class);
    }
}
