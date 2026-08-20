<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Step 2/2 of the additive uuid rollout for `iniciativas` (avances-convite,
 * F41). Re-runs the same idempotent backfill (covers rows inserted outside
 * the `creating` hook, e.g. raw SQL, between the two deploys), asserts zero
 * nulls remain — throwing BEFORE any DDL runs — then applies `not null` +
 * `unique`. Never partially applies: the assert happens strictly before
 * the `Schema::table` call below.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfill();
        $this->assertNoNullsRemain();

        Schema::table('iniciativas', function (Blueprint $table) {
            // Laravel 11+ native ->change() drops any modifier not
            // re-declared here. `uuid` has no default/comment/charset, so
            // re-stating char(36) is enough to keep it safe.
            $table->char('uuid', 36)->nullable(false)->change();
            $table->unique('uuid', 'iniciativas_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropUnique('iniciativas_uuid_unique');
            // Column survives — M1 owns add/drop of the column itself.
            $table->char('uuid', 36)->nullable()->change();
        });
    }

    /**
     * Identical backfill logic to M1 (see that file for the chunkById
     * rationale) — kept as a duplicated, self-contained method rather than
     * a shared helper class so each migration file remains independently
     * readable and re-runnable without cross-file coupling.
     */
    public function backfill(): void
    {
        DB::table('iniciativas')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('iniciativas')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }

    /**
     * Safety net for a null uuid appearing between the backfill above and
     * the DDL below (e.g. a raw insert racing this migration). Fails
     * loudly, before any schema change, so the migration is safely
     * re-runnable rather than leaving a half-applied constraint.
     */
    public function assertNoNullsRemain(): void
    {
        if (DB::table('iniciativas')->whereNull('uuid')->exists()) {
            throw new RuntimeException(
                'iniciativas.uuid: cannot apply unique+not null — one or more rows still have a null uuid after backfill.'
            );
        }
    }
};
