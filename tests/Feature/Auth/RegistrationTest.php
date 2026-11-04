<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertDontSee('auth-showcase');
        $response->assertSee('auth-mobile-brand');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_sends_a_verification_mail_in_the_request_language_de(): void
    {
        Notification::fake();

        $response = $this->post('/de/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'status '.$response->status().' '.json_encode(session('errors')?->all()));
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'E-Mail-Adresse bestätigen – '.config('app.name')
                && str_contains($mail->actionUrl, 'verify-email');
        });
    }
}
