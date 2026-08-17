<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmailJob;
use App\Mail\BienvenidaMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['services.google.frontend_callback_url' => 'http://localhost:3095/auth/google/callback']);
    }

    public function test_registro_encola_el_job_de_bienvenida(): void
    {
        Queue::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Nueva Persona',
            'email' => 'nueva.persona@convites.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'nueva.persona@convites.test')->firstOrFail();

        Queue::assertPushed(SendWelcomeEmailJob::class, fn ($job) => $job->user->is($user));
    }

    public function test_google_usuario_nuevo_recibe_bienvenida_al_completar_registro(): void
    {
        // P47: el usuario nuevo ya no se crea en el callback, se crea en
        // completar-registro — la bienvenida se dispara ahí.
        Queue::fake();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-welcome-1',
            'email' => 'nuevo.google.bienvenida@convites.test',
            'name' => 'Nuevo Por Google',
        ]));

        $callback = $this->get('/api/auth/google/callback?code=fake-code')->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/google/completar-registro', [
            'code' => $query['code'],
            'acepta_terminos' => true,
            'acepta_descargo' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'nuevo.google.bienvenida@convites.test')->firstOrFail();

        Queue::assertPushed(SendWelcomeEmailJob::class, fn ($job) => $job->user->is($user));
    }

    public function test_google_usuario_existente_vinculado_no_recibe_bienvenida_de_nuevo(): void
    {
        $existente = User::factory()->create(['email' => 'ya.tenia.cuenta@convites.test']);
        $existente->assignRole('member');

        Queue::fake();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-welcome-2',
            'email' => 'ya.tenia.cuenta@convites.test',
            'name' => 'Ya Tenia Cuenta',
        ]));

        $this->get('/api/auth/google/callback?code=fake-code')->assertRedirect();

        Queue::assertNotPushed(SendWelcomeEmailJob::class);
    }

    public function test_el_job_realmente_envia_el_mail_de_bienvenida(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Rosa Elena']);

        (new SendWelcomeEmailJob($user))->handle();

        Mail::assertSent(BienvenidaMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && str_contains($mail->render(), 'Rosa Elena');
        });
    }
}
