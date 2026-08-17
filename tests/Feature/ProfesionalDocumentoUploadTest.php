<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Zona;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfesionalDocumentoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registro_con_documentos_los_persiste_en_disco_y_bd(): void
    {
        Storage::fake(config('filesystems.upload'));

        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $certificado = UploadedFile::fake()->create('certificado.pdf', 200, 'application/pdf');
        $foto = UploadedFile::fake()->image('carnet.jpg', 300, 300);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Con Documentos',
            'titulo' => 'Psicóloga',
            'email' => 'documentos@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
            'documentos' => [$certificado, $foto],
        ]);

        $response->assertCreated();

        $profesionalId = $response->json('data.id');
        $this->assertDatabaseCount('profesional_documentos', 2);

        $documento = \App\Models\ProfesionalDocumento::query()
            ->where('profesional_id', $profesionalId)
            ->where('nombre_original', 'certificado.pdf')
            ->firstOrFail();

        Storage::disk($documento->disk)->assertExists($documento->path);
        $this->assertSame('application/pdf', $documento->mime);
    }

    public function test_registro_rechaza_tipo_de_archivo_no_permitido(): void
    {
        Storage::fake(config('filesystems.upload'));

        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $ejecutable = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');

        $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Rechazo',
            'titulo' => 'Psicóloga',
            'email' => 'rechazo@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
            'documentos' => [$ejecutable],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['documentos.0']);

        $this->assertDatabaseCount('profesional_documentos', 0);
    }

    public function test_documentos_visibles_para_dueno_y_moderador_no_para_terceros(): void
    {
        Storage::fake(config('filesystems.upload'));

        $user = User::factory()->create();
        $user->assignRole('member');
        $zona = Zona::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profesionales', [
            'zona_id' => $zona->id,
            'area' => 'psicologia',
            'nombre' => 'Test Visibilidad',
            'titulo' => 'Psicóloga',
            'email' => 'visibilidad@convites.test',
            'modalidad' => 'virtual',
            'disponibilidad' => 'Tardes',
            'descripcion' => 'Descripción de prueba con suficiente longitud.',
            'documentos' => [UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf')],
        ])->assertCreated();

        $profesionalId = $response->json('data.id');
        // Aprobado a mano — `show()` público solo expone profesionales aprobados.
        \App\Models\Profesional::query()->whereKey($profesionalId)->update(['estado' => 'aprobado']);

        // Dueño: vía su propio panel (P29), que sí carga documentos siempre.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mi-perfil-profesional')
            ->assertOk()
            ->assertJsonCount(1, 'data.documentos');

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $this->actingAs($moderator, 'sanctum')
            ->getJson("/api/profesionales/{$profesionalId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.documentos');

        $tercero = User::factory()->create();
        $tercero->assignRole('member');
        $this->actingAs($tercero, 'sanctum')
            ->getJson("/api/profesionales/{$profesionalId}")
            ->assertOk()
            ->assertJsonPath('data.documentos', null);
    }
}
