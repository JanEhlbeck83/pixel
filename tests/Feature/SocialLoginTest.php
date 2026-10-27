<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(string $id, string $email): void
    {
        $social = (new SocialUser)->map(['id' => $id, 'name' => 'Social Tester', 'email' => $email]);
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($social);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_first_google_login_registers_a_user_with_full_free_bucket(): void
    {
        $this->fakeProvider('g-1', 'new@example.test');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('google', $user->provider);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->get('/auth/google/callback')->assertRedirect('/');
        $this->assertSame(1, User::count());
    }

    public function test_google_login_does_not_take_over_existing_password_account(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $this->fakeProvider('g-2', 'taken@example.test');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unconfigured_provider_redirects_back_to_login(): void
    {
        config(['services.facebook.client_id' => null]);

        $this->get('/auth/facebook')->assertRedirect(route('login'));
    }
}
