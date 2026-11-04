<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('auth-showcase');
        $response->assertSee('auth-mobile-brand');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_authenticated_menu_has_a_localized_logout_form(): void
    {
        $user = User::factory()->create();

        foreach (['' => 'Log out', '/de' => 'Abmelden', '/es' => 'Cerrar sesión'] as $prefix => $label) {
            foreach (['/', '/gallery'] as $path) {
                $url = $prefix.$path;
                $html = $this->actingAs($user)
                    ->withHeader('User-Agent', 'Googlebot')
                    ->get($url)
                    ->assertOk()
                    ->getContent();

                $this->assertStringContainsString('method="POST" action="'.url($prefix.'/logout').'"', $html);
                $this->assertStringContainsString('name="_token"', $html);
                $this->assertStringContainsString(e($label), $html);
                $this->assertStringContainsString('M9 21H5', $html);
            }
        }
    }

    public function test_guest_menu_does_not_offer_logout(): void
    {
        $html = $this->get('/de/gallery')->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.url('/de/logout').'"', $html);
    }
}
