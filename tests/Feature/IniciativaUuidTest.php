<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Iniciativa;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1 (avances-convite): iniciativa uuid identity — two-step migration
 * (M1 nullable+backfill, M2 assert-zero-nulls+constrain), `creating` hook,
 * resource exposure.
 *
 * M1/M2 own the `uuid` column lifecycle exclusively. These tests manipulate
 * the real migration objects (not a duplicated reimplementation) so the
 * assertions exercise the actual deployed code path. Because MySQL commits
 * DDL implicitly (ALTER TABLE cannot be rolled back), every test that
 * relaxes the constraint MUST restore it (unique + not null, zero nulls)
 * in a finally block — otherwise it would corrupt schema state for every
 * other test in the suite (RefreshDatabase's per-test transaction can't
 * undo DDL on MySQL).
 */
class IniciativaUuidTest extends TestCase
{
    use RefreshDatabase;

    private const M1 = '2026_08_20_110000_add_uuid_to_iniciativas_table.php';

    private const M2 = '2026_08_20_110100_make_iniciativas_uuid_unique_not_null.php';

    private function loadMigration(string $filename): Migration
    {
        return require database_path('migrations/'.$filename);
    }

    /**
     * Restore the fully-constrained (post-M2) state. Idempotent: `up()`'s
     * `unique()` DDL is NOT idempotent on its own (adding the same-named
     * index twice throws), so we only invoke it when the index is
     * actually missing — a test-only concern, since real migrations only
     * ever run once via the migrations table.
     */
    private function restoreConstrainedState(Migration $m1, Migration $m2): void
    {
        $m1->backfill();

        $hasUniqueIndex = collect(Schema::getIndexes('iniciativas'))
            ->contains(fn (array $index) => $index['name'] === 'iniciativas_uuid_unique');

        if (! $hasUniqueIndex) {
            $m2->up();
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rawIniciativaAttrs(array $overrides = []): array
    {
        return array_merge([
            'user_id' => User::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'slug' => 'iniciativa-uuid-test-'.Str::random(8),
            'titulo' => 'Convite de prueba',
            'resumen' => 'Resumen de prueba para el flujo de uuid.',
            'historia' => json_encode(['Historia de prueba.']),
            'urgencia' => 'media',
            'estado' => 'borrador',
            'lugar_convite' => 'Salón comunal',
            'mapa_visible' => true,
            'asistentes_count' => 0,
            'progreso_cache' => 0,
            'destacada' => false,
            'orden_destacada' => 0,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    public function test_backfill_fills_all_null_uuids_and_is_idempotent_without_touching_existing_rows(): void
    {
        $m1 = $this->loadMigration(self::M1);
        $m2 = $this->loadMigration(self::M2);

        // Relax the schema back to the pre-M2 (post-M1) state so we can
        // insert rows with uuid = null, exactly as a pre-migration prod
        // table would look.
        $m2->down();

        try {
            $existingId = DB::table('iniciativas')->insertGetId(
                $this->rawIniciativaAttrs(['uuid' => (string) Str::uuid()])
            );
            $existingBefore = DB::table('iniciativas')->where('id', $existingId)->first();

            $pendingIds = [];
            for ($i = 0; $i < 3; $i++) {
                $pendingIds[] = DB::table('iniciativas')->insertGetId(
                    $this->rawIniciativaAttrs(['uuid' => null])
                );
            }

            $m1->backfill();

            $afterFirst = DB::table('iniciativas')->whereIn('id', $pendingIds)->get(['id', 'uuid']);
            $this->assertCount(3, $afterFirst);
            foreach ($afterFirst as $row) {
                $this->assertNotNull($row->uuid);
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                    $row->uuid
                );
            }
            $this->assertCount(3, $afterFirst->pluck('uuid')->unique());
            $snapshotAfterFirst = $afterFirst->pluck('uuid', 'id');

            // Run again: must be a no-op (idempotent).
            $m1->backfill();

            $afterSecond = DB::table('iniciativas')->whereIn('id', $pendingIds)->get(['id', 'uuid']);
            foreach ($afterSecond as $row) {
                $this->assertSame($snapshotAfterFirst[$row->id], $row->uuid);
            }

            $existingAfter = DB::table('iniciativas')->where('id', $existingId)->first();
            $this->assertSame($existingBefore->uuid, $existingAfter->uuid);
            $this->assertSame($existingBefore->updated_at, $existingAfter->updated_at);
        } finally {
            // Restore full-strength schema for every other test in the suite.
            $this->restoreConstrainedState($m1, $m2);
        }
    }

    public function test_constraint_migration_fails_loudly_and_never_partially_applies_when_a_null_remains(): void
    {
        $m2 = $this->loadMigration(self::M2);

        $m2->down();

        try {
            DB::table('iniciativas')->insertGetId($this->rawIniciativaAttrs(['uuid' => null]));

            $thrown = null;

            try {
                $m2->assertNoNullsRemain();
            } catch (\RuntimeException $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, 'Expected assertNoNullsRemain() to throw while a null uuid remains.');

            // Never partially applies: schema must still accept a null uuid insert.
            $stillNullable = DB::table('iniciativas')->insertGetId($this->rawIniciativaAttrs(['uuid' => null]));
            $this->assertNull(DB::table('iniciativas')->where('id', $stillNullable)->value('uuid'));
        } finally {
            $m1 = $this->loadMigration(self::M1);
            $this->restoreConstrainedState($m1, $m2);
        }
    }

    public function test_constraint_migration_up_applies_cleanly_once_backfill_is_complete(): void
    {
        $m2 = $this->loadMigration(self::M2);

        $m2->down();

        try {
            DB::table('iniciativas')->insertGetId($this->rawIniciativaAttrs(['uuid' => null]));

            // up() re-runs the backfill itself before asserting/constraining.
            $m2->up();

            $this->assertSame(0, DB::table('iniciativas')->whereNull('uuid')->count());
        } finally {
            $m1 = $this->loadMigration(self::M1);
            $this->restoreConstrainedState($m1, $m2);
        }
    }

    public function test_new_iniciativa_auto_assigns_a_valid_uuid_on_create(): void
    {
        $iniciativa = Iniciativa::factory()->create();

        $this->assertNotNull($iniciativa->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $iniciativa->uuid
        );
    }

    public function test_iniciativa_resource_exposes_uuid(): void
    {
        $iniciativa = Iniciativa::factory()->publicada()->create();

        $this->assertNotNull($iniciativa->uuid);

        $response = $this->getJson('/api/iniciativas/'.$iniciativa->slug);

        $response->assertOk();
        $response->assertJsonPath('data.uuid', $iniciativa->uuid);
    }
}
