<?php

namespace Tests\Feature;

use App\Enums\EstadoCentro;
use App\Enums\EstadoIniciativa;
use App\Enums\TipoCentro;
use App\Enums\Urgencia;
use App\Models\Centro;
use App\Models\Iniciativa;
use App\Models\IniciativaItem;
use App\Models\IniciativaPuntoAcopio;
use App\Models\Municipio;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\ColombiaGeoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API bot/MCP (/api/bot/v1): solo lectura, token propio, sin PII de aportantes
 * ni de profesionales; solo convites activos (publicada|en_curso).
 */
class BotV1ApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-bot-token-convites';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ColombiaGeoSeeder::class);
        config([
            'services.bot.token' => self::TOKEN,
            'services.bot.frontend_url' => 'https://convites.co',
        ]);
    }

    private function botGet(string $uri, array $query = [])
    {
        $qs = $query === [] ? '' : '?'.http_build_query($query);

        return $this->withToken(self::TOKEN)
            ->getJson('/api/bot/v1'.$uri.$qs);
    }

    public function test_sin_token_da_401(): void
    {
        $this->getJson('/api/bot/v1/health')->assertUnauthorized();
        $this->getJson('/api/bot/v1/convites')->assertUnauthorized();
    }

    public function test_token_invalido_da_401(): void
    {
        $this->withToken('wrong')
            ->getJson('/api/bot/v1/health')
            ->assertUnauthorized();
    }

    public function test_health_ok(): void
    {
        $this->botGet('/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('service', 'convites-bot');
    }

    public function test_convites_lista_solo_publicados_slim(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();

        $pub = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'titulo' => 'Techos de emergencia',
            'urgencia' => Urgencia::Alta,
            'progreso_cache' => 20,
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $pub->id,
            'nombre' => 'Cemento',
            'unidad' => 'bultos',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 2,
            'orden' => 1,
        ]);

        Iniciativa::factory()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Borrador,
            'titulo' => 'Borrador oculto',
        ]);

        $res = $this->botGet('/convites', ['q' => 'Techos', 'limit' => 5])
            ->assertOk()
            ->assertJsonPath('meta.limit', 5);

        $slugs = collect($res->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($pub->slug));
        $this->assertFalse($slugs->contains(fn ($s) => str_contains((string) $s, 'borrador')));

        $row = collect($res->json('data'))->firstWhere('slug', $pub->slug);
        $this->assertSame('https://convites.co/iniciativa/'.$pub->slug, $row['url']);
        $this->assertArrayHasKey('faltantes_resumen', $row);
        $this->assertArrayNotHasKey('telefono_contacto', $row);
        $this->assertArrayNotHasKey('creador', $row);
    }

    public function test_detalle_convite_sin_pii_organizador(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $creador = User::factory()->create();
        $ini = Iniciativa::factory()->publicada()->create([
            'user_id' => $creador->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'telefono_contacto' => '+57 300 111 2233',
            'persona_responsable' => 'Secreto',
        ]);
        IniciativaItem::query()->create([
            'iniciativa_id' => $ini->id,
            'nombre' => 'Malla',
            'unidad' => 'metros',
            'cantidad_meta' => 10,
            'cantidad_aportada' => 0,
            'orden' => 1,
        ]);
        IniciativaPuntoAcopio::query()->create([
            'iniciativa_id' => $ini->id,
            'municipio_id' => $municipio->id,
            'nombre' => 'Punto norte',
            'direccion' => 'Calle 1 #2-3',
            'horario' => '8am-5pm',
            'contacto' => '3009998877',
            'orden' => 1,
        ]);

        $res = $this->botGet('/convites/'.$ini->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $ini->slug)
            ->assertJsonPath('data.items.0.nombre', 'Malla')
            ->assertJsonPath('data.puntos_acopio.0.nombre', 'Punto norte');

        $data = $res->json('data');
        $this->assertArrayNotHasKey('telefono_contacto', $data);
        $this->assertArrayNotHasKey('persona_responsable', $data);
        $this->assertArrayNotHasKey('verificacion', $data);
    }

    public function test_detalle_borrador_404(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $ini = Iniciativa::factory()->create([
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Borrador,
        ]);

        $this->botGet('/convites/'.$ini->slug)->assertNotFound();
    }

    public function test_centros_activos(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $zona = Zona::query()->first() ?? Zona::factory()->create();

        Centro::query()->create([
            'tipo' => TipoCentro::Acopio,
            'nombre' => 'Acopio Central',
            'zona_id' => $zona->id,
            'municipio_id' => $municipio->id,
            'direccion' => 'Carrera 7',
            'horario' => '24h',
            'telefono' => '3210001111',
            'estado' => EstadoCentro::Abierto,
            'descripcion' => 'Punto demo',
            'necesita' => ['agua'],
            'no_recibe' => ['ropa mojada'],
            'emergencia' => true,
            'activo' => true,
            'orden' => 1,
        ]);

        $this->botGet('/centros', ['tipo' => 'acopio', 'municipio' => $municipio->nombre])
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Acopio Central')
            ->assertJsonPath('data.0.direccion', 'Carrera 7')
            ->assertJsonPath('data.0.telefono', '3210001111');
    }

    public function test_profesionales_no_existe_en_bot_api(): void
    {
        $this->botGet('/profesionales')->assertNotFound();
    }

    public function test_convite_cerrado_no_aparece(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $cerrada = Iniciativa::factory()->create([
            'municipio_id' => $municipio->id,
            'zona_id' => null,
            'estado' => EstadoIniciativa::Cerrada,
            'titulo' => 'Convite ya cerrado bot',
            'slug' => 'convite-ya-cerrado-bot',
        ]);

        $this->botGet('/convites', ['q' => 'cerrado bot'])
            ->assertOk()
            ->assertJsonMissing(['slug' => $cerrada->slug]);

        $this->botGet('/convites/'.$cerrada->slug)->assertNotFound();
    }
}
