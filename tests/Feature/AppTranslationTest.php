<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_pages_are_translated(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot your password?')->assertSee('Sign in');
        $this->get('/de/login')->assertOk()->assertSee('Passwort vergessen?')->assertSee('Anmelden');
        $this->get('/es/login')->assertOk()->assertSee('¿Olvidaste tu contraseña?')->assertSee('Iniciar sesión');
        $this->get('/es/register')->assertOk()->assertSee('Crear cuenta');
        $this->get('/forgot-password')->assertOk()->assertSee('Email password reset link');
    }

    public function test_profile_is_translated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Purchases &amp; payments', false);
        $this->actingAs($user)->get('/de/profile')->assertOk()->assertSee('Käufe &amp; Zahlungen', false);
        $this->actingAs($user)->get('/es/profile')->assertOk()->assertSee('Compras y pagos');
    }

    public function test_activity_is_localized_at_read_time(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);
        Activity::create(['user_id' => $user->id, 'action' => 'painted', 'type' => 'pixel_set', 'params' => ['name' => 'Anna'], 'description' => 'Anna hat ein Pixel gesetzt.']);
        Activity::create(['user_id' => $user->id, 'action' => 'painted', 'description' => 'Bob hat ein Pixel gesetzt.']);

        $es = $this->withHeader('X-App-Locale', 'es')->getJson('/api/activity')->assertOk()->json('activity.*.description');
        $this->assertContains('Anna ha colocado un píxel.', $es);
        $this->assertContains('Bob ha colocado un píxel.', $es);

        $en = $this->withHeader('X-App-Locale', 'en')->getJson('/api/activity')->json('activity.*.description');
        $this->assertContains('Anna placed a pixel.', $en);

        $de = $this->withHeader('X-App-Locale', 'de')->getJson('/api/activity')->json('activity.*.description');
        $this->assertContains('Bob hat ein Pixel gesetzt.', $de);
    }

    public function test_password_reset_mail_uses_request_locale(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->withHeader('X-App-Locale', 'es')->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            app()->setLocale('es');
            $mail = $notification->toMail($user);

            return str_starts_with($mail->subject, 'Restablecer contraseña');
        });
    }
}
