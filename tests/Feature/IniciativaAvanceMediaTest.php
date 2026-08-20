<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3 (avances-convite): media upload/delete for an avance. Spec
 * requirement "Media constraints" — video MIME + duracion_segundos<=120,
 * image <=5MB; `tipo` is ALWAYS derived server-side from the MIME (D-H).
 */
class IniciativaAvanceMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function autor(): User
    {
        return User::query()->where('email', 'member@convites.test')->firstOrFail();
    }

    private function crearAvanceDe(User $autor): array
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        $iniciativa = Iniciativa::factory()->create([
            'user_id' => $autor->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);

        $avance = IniciativaAvance::factory()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $autor->id,
        ]);

        return [$iniciativa, $avance];
    }

    public function test_sube_imagen_valida_y_tipo_se_infiere_del_mime(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->image('foto.jpg', 300, 200),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'imagen')
            ->assertJsonPath('data.ancho', 300)
            ->assertJsonPath('data.alto', 200);

        $this->assertDatabaseHas('iniciativa_avance_media', [
            'iniciativa_avance_id' => $avance->id,
            'tipo' => 'imagen',
        ]);
    }

    public function test_imagen_mayor_a_5mb_es_rechazada(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->create('foto.jpg', 5121, 'image/jpeg'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('iniciativa_avance_media', 0);
    }

    public function test_video_valido_dentro_del_limite_de_duracion_es_aceptado(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
                'duracion_segundos' => 90,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'video')
            ->assertJsonPath('data.duracion_segundos', 90);
    }

    public function test_video_mayor_a_120_segundos_es_rechazado(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
                'duracion_segundos' => 121,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['duracion_segundos']);

        $this->assertDatabaseCount('iniciativa_avance_media', 0);
    }

    public function test_tipo_enviado_por_el_cliente_es_ignorado_se_infiere_del_mime(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        // Client tries to claim this image is a "video" — server must ignore it.
        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->image('foto.jpg'),
                'tipo' => 'video',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'imagen');
    }

    public function test_eliminar_media_borra_el_archivo_del_disco(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $upload = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->image('foto.jpg'),
            ])->assertCreated();

        $mediaId = $upload->json('data.id');
        $media = $avance->media()->firstOrFail();

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media/{$mediaId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('iniciativa_avance_media', ['id' => $mediaId]);
        Storage::disk(config('filesystems.upload'))->assertMissing($media->path);
    }

    public function test_no_autor_no_puede_subir_media(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = $this->autor();
        [$iniciativa, $avance] = $this->crearAvanceDe($autor);

        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->uuid}/avances/{$avance->id}/media", [
                'archivo' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('iniciativa_avance_media', 0);
    }
}
