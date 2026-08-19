<?php

namespace Tests\Feature;

use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [P52] Cubre la migration idempotente que activa el catálogo geo completo
 * de Colombia (departamentos + municipios). Ver docs/pendientes.md [P52] /
 * docs/finalizados.md para el detalle del ticket.
 */
class ActivarCatalogoGeoColombiaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_activa_todos_los_departamentos_y_municipios_sin_tocar_otras_tablas(): void
    {
        $deptoActivoId = $this->insertDepartamento(activo: true);
        $deptoInactivoId1 = $this->insertDepartamento(activo: false);
        $deptoInactivoId2 = $this->insertDepartamento(activo: false);

        $this->insertMunicipio($deptoInactivoId1, activo: false);
        $this->insertMunicipio($deptoInactivoId2, activo: false);
        $this->insertMunicipio($deptoActivoId, activo: true);

        // Datos "vivos" que la migración nunca debe tocar.
        $user = User::factory()->create();
        Iniciativa::factory()->create(['user_id' => $user->id]);

        $usersCountAntes = DB::table('users')->count();
        $iniciativasCountAntes = DB::table('iniciativas')->count();

        $this->assertSame(2, DB::table('departamentos')->where('activo', false)->count());
        $this->assertSame(2, DB::table('municipios')->where('activo', false)->count());

        $this->runActivarCatalogoGeoMigrationUp();

        $this->assertSame(0, DB::table('departamentos')->where('activo', false)->count(), 'Quedaron departamentos con activo=false tras la migración.');
        $this->assertSame(0, DB::table('municipios')->where('activo', false)->count(), 'Quedaron municipios con activo=false tras la migración.');
        $this->assertGreaterThanOrEqual(3, DB::table('departamentos')->count());
        $this->assertGreaterThanOrEqual(3, DB::table('municipios')->count());

        // La migración no debió tocar otras tablas (users / iniciativas).
        $this->assertSame($usersCountAntes, DB::table('users')->count());
        $this->assertSame($iniciativasCountAntes, DB::table('iniciativas')->count());

        // Idempotencia: correrla otra vez no debe romper ni cambiar nada.
        $this->runActivarCatalogoGeoMigrationUp();
        $this->assertSame(0, DB::table('departamentos')->where('activo', false)->count());
        $this->assertSame(0, DB::table('municipios')->where('activo', false)->count());
        $this->assertSame($usersCountAntes, DB::table('users')->count());
        $this->assertSame($iniciativasCountAntes, DB::table('iniciativas')->count());
    }

    private function insertDepartamento(bool $activo): int
    {
        $externalId = random_int(100000, 999999);

        return (int) DB::table('departamentos')->insertGetId([
            'external_id' => $externalId,
            'nombre' => "Depto Test {$externalId}",
            'slug' => Str::slug("depto-test-{$externalId}"),
            'codigo' => null,
            'activo' => $activo,
            'orden' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMunicipio(int $departamentoId, bool $activo): int
    {
        $externalId = random_int(100000, 999999);

        return (int) DB::table('municipios')->insertGetId([
            'departamento_id' => $departamentoId,
            'external_id' => $externalId,
            'nombre' => "Municipio Test {$externalId}",
            'slug' => Str::slug("municipio-test-{$externalId}"),
            'activo' => $activo,
            'orden' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Instancia la migration directamente desde su archivo (patrón estándar
     * de Laravel para testear una migration puntual) y corre su up().
     */
    private function runActivarCatalogoGeoMigrationUp(): void
    {
        $matches = glob(database_path('migrations/*_activar_catalogo_geo_colombia_completo.php'));

        $this->assertNotEmpty(
            $matches,
            'Falta la migration que activa el catálogo geo completo de Colombia (ver docs/pendientes.md [P52]).'
        );

        $migration = require $matches[0];
        $migration->up();
    }
}
