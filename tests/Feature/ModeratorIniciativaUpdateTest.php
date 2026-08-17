<?php

namespace Tests\Feature;

use App\Enums\EstadoIniciativa;
use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorIniciativaUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_moderator_can_update_iniciativa_in_assigned_municipio(): void
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();
        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();
        $moderator->municipiosAsignados()->syncWithoutDetaching([$municipio->id]);

        $iniciativa = Iniciativa::query()
            ->where('estado', EstadoIniciativa::Publicada)
            ->where('municipio_id', $municipio->id)
            ->first();

        if (! $iniciativa) {
            $iniciativa = Iniciativa::query()
                ->where('estado', EstadoIniciativa::Publicada)
                ->firstOrFail();
            $iniciativa->update(['municipio_id' => $municipio->id]);
            $iniciativa->refresh();
        }

        $payload = $this->updatePayload($iniciativa, [
            'titulo' => 'Título editado por moderador',
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->putJson('/api/iniciativas/'.$iniciativa->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Título editado por moderador');
    }

    public function test_moderator_cannot_update_iniciativa_outside_municipio(): void
    {
        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();
        $assigned = $moderator->municipiosAsignados()->pluck('municipios.id')->all();
        $outside = Municipio::query()
            ->where('activo', true)
            ->when(count($assigned) > 0, fn ($q) => $q->whereNotIn('id', $assigned))
            ->firstOrFail();

        $iniciativa = Iniciativa::query()
            ->where('estado', EstadoIniciativa::Publicada)
            ->firstOrFail();
        $iniciativa->update(['municipio_id' => $outside->id]);
        $iniciativa->refresh();

        $payload = $this->updatePayload($iniciativa, [
            'titulo' => 'No debería guardar',
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->putJson('/api/iniciativas/'.$iniciativa->id, $payload)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Iniciativa $iniciativa, array $overrides = []): array
    {
        $iniciativa->loadMissing(['items', 'puntosAcopio', 'categoria', 'municipio']);

        return array_merge([
            'version' => $iniciativa->version,
            'municipio_id' => $iniciativa->municipio_id,
            'categoria_id' => $iniciativa->categoria_id,
            'titulo' => $iniciativa->titulo,
            'resumen' => $iniciativa->resumen,
            'historia' => $iniciativa->historia ?: ['Historia'],
            'urgencia' => $iniciativa->urgencia?->value ?? 'media',
            'lugar_convite' => $iniciativa->lugar_convite,
            'lugar_exacto' => $iniciativa->lugar_exacto,
            'fecha_convite' => $iniciativa->fecha_convite?->toDateString(),
            'fecha_limite_aportes' => $iniciativa->fecha_limite_aportes?->toDateString(),
            'persona_responsable' => $iniciativa->persona_responsable ?? 'Responsable',
            'quien_respalda' => $iniciativa->quien_respalda ?? 'JAC',
            'telefono_contacto' => $iniciativa->telefono_contacto ?? '+57 300 000 0000',
            'items' => $iniciativa->items->map(fn ($it) => [
                'nombre' => $it->nombre,
                'unidad' => $it->unidad,
                'cantidad_meta' => $it->cantidad_meta,
                'orden' => $it->orden,
            ])->all(),
        ], $overrides);
    }
}
