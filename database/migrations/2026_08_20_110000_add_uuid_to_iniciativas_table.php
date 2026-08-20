<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Step 1/2 of the additive uuid rollout for `iniciativas` (avances-convite,
 * F41). Adds the column nullable and backfills it — safe to run on a
 * populated prod table regardless of row count, never fails mid-DDL.
 *
 * `unique` + `not null` are applied separately in the follow-up migration
 * (`..._110100_make_iniciativas_uuid_unique_not_null.php`) only after every
 * row is confirmed backfilled. This migration owns the column's lifecycle
 * (add here, drop here); the follow-up only touches the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable()->after('id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('iniciativas', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }

    /**
     * Idempotent, chunked backfill via the query builder (no Eloquent):
     * no model events fire and `updated_at` is not mutated on rows that
     * already have a uuid. Uses `chunkById` — NOT `chunk` — because the
     * callback mutates the very column the WHERE clause filters on
     * (`whereNull('uuid')`); offset-based `chunk()` would skip roughly
     * half the remaining rows as each page shrinks the matching set.
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
};
