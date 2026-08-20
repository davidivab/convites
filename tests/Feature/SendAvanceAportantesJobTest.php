<?php

namespace Tests\Feature;

use App\Enums\EstadoAporte;
use App\Jobs\SendAvanceAportantesJob;
use App\Mail\AvancePublicadoMail;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Models\IniciativaAvance;
use App\Models\NotificacionPreferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 4 (avances-convite): `SendAvanceAportantesJob` fans out the
 * publish notification to aportantes with `confirmado|cumplido` estado,
 * at-most-once (claim `notificado_at` before send — D-F), honoring the
 * `email_avances` opt-out with a missing-row fallback (D-B) and never
 * excluding anonymous aportantes (confirmed decision 4).
 */
class SendAvanceAportantesJobTest extends TestCase
{
    use RefreshDatabase;

    private function crearIniciativaPublicada(): Iniciativa
    {
        return Iniciativa::factory()->publicada()->create();
    }

    private function crearAporte(Iniciativa $iniciativa, User $user, EstadoAporte $estado, bool $anonimo = false): Aporte
    {
        return Aporte::query()->create([
            'iniciativa_id' => $iniciativa->id,
            'user_id' => $user->id,
            'estado' => $estado,
            'asiste_al_convite' => true,
            'anonimo' => $anonimo,
            'confirmado_at' => now(),
        ]);
    }

    public function test_envia_correo_a_aportante_confirmado_sin_fila_de_preferencia(): void
    {
        Mail::fake();

        $iniciativa = $this->crearIniciativaPublicada();
        $aportante = User::factory()->create();
        $this->crearAporte($iniciativa, $aportante, EstadoAporte::Confirmado);

        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'notificar_aportantes' => true,
        ]);

        (new SendAvanceAportantesJob($avance))->handle();

        Mail::assertSent(AvancePublicadoMail::class, fn ($mail) => $mail->hasTo($aportante->email));
    }

    public function test_no_envia_a_aportante_con_email_avances_desactivado(): void
    {
        Mail::fake();

        $iniciativa = $this->crearIniciativaPublicada();
        $aportante = User::factory()->create();
        NotificacionPreferencia::query()->create([
            'user_id' => $aportante->id,
            'email_avances' => false,
        ]);
        $this->crearAporte($iniciativa, $aportante, EstadoAporte::Confirmado);

        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'notificar_aportantes' => true,
        ]);

        (new SendAvanceAportantesJob($avance))->handle();

        Mail::assertNotSent(AvancePublicadoMail::class);
    }

    public function test_envia_a_aportante_anonimo_igual_que_a_cualquier_otro(): void
    {
        Mail::fake();

        $iniciativa = $this->crearIniciativaPublicada();
        $aportante = User::factory()->create();
        $this->crearAporte($iniciativa, $aportante, EstadoAporte::Confirmado, anonimo: true);

        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'notificar_aportantes' => true,
        ]);

        (new SendAvanceAportantesJob($avance))->handle();

        Mail::assertSent(AvancePublicadoMail::class, fn ($mail) => $mail->hasTo($aportante->email));
    }

    public function test_no_envia_a_aportante_cancelado(): void
    {
        // Nota: `EstadoAporte` solo define confirmado|cancelado|cumplido — no
        // existe un estado "pendiente" en este dominio (verificado contra el
        // enum real), así que el único estado excluido distinto de
        // confirmado/cumplido que puede probarse es cancelado.
        Mail::fake();

        $iniciativa = $this->crearIniciativaPublicada();
        $cancelado = User::factory()->create();
        $this->crearAporte($iniciativa, $cancelado, EstadoAporte::Cancelado);

        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'notificar_aportantes' => true,
        ]);

        (new SendAvanceAportantesJob($avance))->handle();

        Mail::assertNotSent(AvancePublicadoMail::class);
    }

    public function test_reclama_notificado_at_de_forma_atomica_y_no_reenvia_en_segunda_ejecucion(): void
    {
        Mail::fake();

        $iniciativa = $this->crearIniciativaPublicada();
        $aportante = User::factory()->create();
        $this->crearAporte($iniciativa, $aportante, EstadoAporte::Cumplido);

        $avance = IniciativaAvance::factory()->publicado()->create([
            'iniciativa_id' => $iniciativa->id,
            'notificar_aportantes' => true,
        ]);

        (new SendAvanceAportantesJob($avance))->handle();
        Mail::assertSent(AvancePublicadoMail::class, 1);
        $this->assertNotNull($avance->fresh()->notificado_at);

        (new SendAvanceAportantesJob($avance->fresh()))->handle();

        Mail::assertSent(AvancePublicadoMail::class, 1);
    }
}
