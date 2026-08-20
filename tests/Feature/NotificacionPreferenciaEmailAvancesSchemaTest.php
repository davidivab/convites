<?php

namespace Tests\Feature;

use App\Models\NotificacionPreferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 4 (avances-convite): M5 adds `email_avances` to
 * `notificacion_preferencias`, defaulting to true (opted-in) [Spec:
 * email_avances opt-out with missing-row fallback].
 */
class NotificacionPreferenciaEmailAvancesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notificacion_preferencias_table_has_email_avances_column(): void
    {
        $this->assertTrue(Schema::hasColumn('notificacion_preferencias', 'email_avances'));
    }

    public function test_email_avances_defaults_to_true_when_omitted(): void
    {
        $user = User::factory()->create();

        $preferencia = NotificacionPreferencia::query()->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue($preferencia->fresh()->email_avances);
    }

    public function test_email_avances_is_fillable_and_cast_to_boolean(): void
    {
        $user = User::factory()->create();

        $preferencia = NotificacionPreferencia::query()->create([
            'user_id' => $user->id,
            'email_avances' => false,
        ]);

        $this->assertFalse($preferencia->fresh()->email_avances);
        $this->assertIsBool($preferencia->fresh()->email_avances);
    }
}
