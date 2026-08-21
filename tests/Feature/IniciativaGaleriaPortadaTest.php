<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P53 (parte 3) — portada + galería de imágenes de una iniciativa.
 */
class IniciativaGaleriaPortadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function crearIniciativaDe(User $autor): Iniciativa
    {
        $municipio = Municipio::query()->where('activo', true)->firstOrFail();

        return Iniciativa::factory()->create([
            'user_id' => $autor->id,
            'municipio_id' => $municipio->id,
            'zona_id' => null,
        ]);
    }

    // --- Portada -----------------------------------------------------

    public function test_autor_sube_portada_y_recibe_recurso_completo(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/imagen-portada", [
                'imagen' => UploadedFile::fake()->image('portada.jpg', 800, 600),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $iniciativa->id);

        $path = $response->json('data.imagen_path');
        $this->assertNotNull($path);

        $fresh = $iniciativa->fresh();
        $this->assertNotNull($fresh->imagen_path);
        Storage::disk(config('filesystems.upload'))->assertExists($fresh->imagen_path);
    }

    public function test_no_autor_no_puede_subir_portada(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/imagen-portada", [
                'imagen' => UploadedFile::fake()->image('portada.jpg'),
            ])
            ->assertForbidden();

        $this->assertNull($iniciativa->fresh()->imagen_path);
    }

    public function test_moderador_puede_subir_portada_de_iniciativa_ajena(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $moderator = User::query()->where('email', 'moderator@convites.test')->firstOrFail();
        $moderator->municipiosAsignados()->syncWithoutDetaching([$iniciativa->municipio_id]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/imagen-portada", [
                'imagen' => UploadedFile::fake()->image('portada.jpg'),
            ])
            ->assertOk();

        $this->assertNotNull($iniciativa->fresh()->imagen_path);
    }

    public function test_portada_requiere_archivo_de_imagen_valido(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/imagen-portada", [
                'imagen' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['imagen']);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/imagen-portada", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['imagen']);
    }

    // --- Galería: upload ----------------------------------------------

    public function test_autor_sube_imagen_a_galeria_y_recibe_shape_exacto_con_version_de_iniciativa(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);
        $this->assertSame(1, $iniciativa->version);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto1.jpg', 400, 300),
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'tipo', 'url', 'orden', 'ancho', 'alto', 'duracion_segundos', 'version']])
            ->assertJsonPath('data.tipo', 'imagen')
            ->assertJsonPath('data.orden', 1)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.ancho', 400)
            ->assertJsonPath('data.alto', 300)
            ->assertJsonPath('data.duracion_segundos', null);

        $this->assertNotNull($response->json('data.url'));
        $this->assertSame(2, $iniciativa->fresh()->version);
        $this->assertDatabaseCount('iniciativa_galeria', 1);
    }

    public function test_orden_de_galeria_autoincrementa_en_uploads_sucesivos(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $r1 = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto1.jpg'),
            ])->assertCreated();
        $this->assertSame(1, $r1->json('data.orden'));
        $this->assertSame(2, $r1->json('data.version'));

        $r2 = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto2.jpg'),
            ])->assertCreated();
        $this->assertSame(2, $r2->json('data.orden'));
        $this->assertSame(3, $r2->json('data.version'));

        $r3 = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto3.jpg'),
            ])->assertCreated();
        $this->assertSame(3, $r3->json('data.orden'));
        $this->assertSame(4, $r3->json('data.version'));

        $this->assertDatabaseCount('iniciativa_galeria', 3);
    }

    public function test_no_autor_no_puede_subir_a_galeria(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('iniciativa_galeria', 0);
    }

    public function test_galeria_requiere_archivo_de_imagen_valido(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    // --- Galería: video (P54, mirror de avances) ------------------------

    public function test_autor_sube_video_a_galeria_y_tipo_se_infiere_del_mime(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $response = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
                'duracion_segundos' => 90,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tipo', 'video')
            ->assertJsonPath('data.duracion_segundos', 90)
            ->assertJsonPath('data.ancho', null)
            ->assertJsonPath('data.alto', null);

        $this->assertDatabaseHas('iniciativa_galeria', [
            'iniciativa_id' => $iniciativa->id,
            'tipo' => 'video',
            'duracion_segundos' => 90,
        ]);
    }

    public function test_video_en_galeria_sin_duracion_segundos_es_rechazado(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duracion_segundos']);

        $this->assertDatabaseCount('iniciativa_galeria', 0);
    }

    public function test_video_en_galeria_mayor_a_50mb_es_rechazado(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->create('clip.mp4', 51201, 'video/mp4'),
                'duracion_segundos' => 90,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('iniciativa_galeria', 0);
    }

    public function test_video_en_galeria_con_mimetype_no_permitido_es_rechazado(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->create('clip.avi', 2048, 'video/x-msvideo'),
                'duracion_segundos' => 90,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('iniciativa_galeria', 0);
    }

    // --- Galería: delete ------------------------------------------------

    public function test_autor_elimina_item_de_galeria_y_recibe_recurso_sin_ese_item(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $upload = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto1.jpg'),
            ])->assertCreated();
        $galeriaId = $upload->json('data.id');
        $this->assertSame(2, $iniciativa->fresh()->version);

        $upload2 = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto2.jpg'),
            ])->assertCreated();
        $galeriaId2 = $upload2->json('data.id');
        $this->assertSame(3, $iniciativa->fresh()->version);

        $delete = $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->id}/galeria/{$galeriaId}");

        $delete->assertOk()
            ->assertJsonPath('data.id', $iniciativa->id)
            ->assertJsonCount(1, 'data.galeria')
            ->assertJsonPath('data.galeria.0.id', $galeriaId2)
            ->assertJsonPath('data.version', 4);

        $this->assertSame(4, $iniciativa->fresh()->version);
        $this->assertDatabaseMissing('iniciativa_galeria', ['id' => $galeriaId]);
        $this->assertDatabaseHas('iniciativa_galeria', ['id' => $galeriaId2]);
    }

    public function test_no_autor_no_puede_eliminar_de_galeria(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativa = $this->crearIniciativaDe($autor);

        $upload = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativa->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto1.jpg'),
            ])->assertCreated();
        $galeriaId = $upload->json('data.id');

        $tercero = User::factory()->create();
        $tercero->assignRole('member');

        $this->actingAs($tercero, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativa->id}/galeria/{$galeriaId}")
            ->assertForbidden();

        $this->assertDatabaseHas('iniciativa_galeria', ['id' => $galeriaId]);
    }

    public function test_eliminar_galeria_id_ajeno_a_la_iniciativa_da_404(): void
    {
        Storage::fake(config('filesystems.upload'));

        $autor = User::query()->where('email', 'member@convites.test')->firstOrFail();
        $iniciativaA = $this->crearIniciativaDe($autor);
        $iniciativaB = $this->crearIniciativaDe($autor);

        $upload = $this->actingAs($autor, 'sanctum')
            ->postJson("/api/iniciativas/{$iniciativaA->id}/galeria", [
                'archivo' => UploadedFile::fake()->image('foto1.jpg'),
            ])->assertCreated();
        $galeriaId = $upload->json('data.id');

        $this->actingAs($autor, 'sanctum')
            ->deleteJson("/api/iniciativas/{$iniciativaB->id}/galeria/{$galeriaId}")
            ->assertNotFound();

        $this->assertDatabaseHas('iniciativa_galeria', ['id' => $galeriaId]);
    }
}
