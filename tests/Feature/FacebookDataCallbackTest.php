<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookDataCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook.client_secret' => self::SECRET]);
    }

    private function signed(string $facebookId, string $secret = self::SECRET): string
    {
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $payload = $b64(json_encode(['algorithm' => 'HMAC-SHA256', 'user_id' => $facebookId]));

        return $b64(hash_hmac('sha256', $payload, $secret, true)).'.'.$payload;
    }

    private function facebookUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['provider' => 'facebook', 'provider_id' => '12345'])->save();

        return $user;
    }

    public function test_data_deletion_removes_user_and_returns_status_url(): void
    {
        $user = $this->facebookUser();

        $response = $this->post('/auth/facebook/data-deletion', ['signed_request' => $this->signed('12345')]);

        $response->assertOk()->assertJsonStructure(['url', 'confirmation_code']);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->get($response->json('url'))->assertOk()->assertSee($response->json('confirmation_code'));
    }

    public function test_invalid_signature_is_rejected_and_user_kept(): void
    {
        $user = $this->facebookUser();

        $this->post('/auth/facebook/data-deletion', ['signed_request' => $this->signed('12345', 'wrong')])->assertStatus(400);
        $this->post('/auth/facebook/deauthorize', ['signed_request' => 'garbage'])->assertStatus(400);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deauthorize_only_unlinks_facebook(): void
    {
        $user = $this->facebookUser();

        $this->post('/auth/facebook/deauthorize', ['signed_request' => $this->signed('12345')])->assertOk();

        $user->refresh();
        $this->assertNull($user->provider);
        $this->assertNull($user->provider_id);
    }

    public function test_unknown_status_code_is_404(): void
    {
        $this->get('/data-deletion/status/unknown')->assertNotFound();
    }
}
