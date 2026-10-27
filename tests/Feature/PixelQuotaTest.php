<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixelQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function paint(User $user, int $x, int $y)
    {
        $lock = $this->actingAs($user)->postJson('/api/pixels/lock', ['x' => $x, 'y' => $y, 'width' => 1, 'height' => 1]);
        if ($lock->status() !== 200) {
            return $lock;
        }

        return $this->actingAs($user)->postJson('/api/pixels', ['x' => $x, 'y' => $y, 'color' => '#ff0000']);
    }

    public function test_guest_status_counts_down_from_50(): void
    {
        $this->getJson('/api/pixels/status')
            ->assertJsonPath('can_edit', true)
            ->assertJsonPath('free', 50);

        $this->postJson('/api/pixels', ['x' => 1, 'y' => 1, 'color' => '#ff0000'])->assertOk();

        $this->getJson('/api/pixels/status')
            ->assertJsonPath('can_edit', true)
            ->assertJsonPath('free', 49)
            ->assertJsonPath('authenticated', false);
    }

    public function test_new_user_starts_with_50_free_pixels(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/pixels/status')
            ->assertJsonPath('free', 50)
            ->assertJsonPath('max_free', 50);
    }

    public function test_user_bucket_empties_and_regenerates_every_30_seconds(): void
    {
        $user = User::factory()->create(['free_pixels' => 1]);

        $this->paint($user, 5, 5)->assertOk();

        $denied = $this->paint($user, 6, 6)->assertStatus(429);
        $denied->assertJsonPath('limit', 'user');
        $this->assertLessThanOrEqual(30, $denied->json('retry_after'));
        $this->assertGreaterThan(25, $denied->json('retry_after'));

        $this->travel(30)->seconds();
        $this->paint($user, 6, 6)->assertOk();
    }

    public function test_bucket_is_capped_at_50(): void
    {
        $user = User::factory()->create(['free_pixels' => 49, 'free_pixels_at' => now()->subHour()]);

        $this->actingAs($user)->getJson('/api/pixels/status')->assertJsonPath('free', 50);
    }

    public function test_credits_are_used_after_free_pixels_and_can_exceed_50(): void
    {
        $user = User::factory()->create(['free_pixels' => 1, 'pixel_credits' => 2]);

        $this->paint($user, 5, 5)->assertOk();
        $this->assertSame(2, $user->fresh()->pixel_credits);

        $this->paint($user, 6, 6)->assertOk();
        $this->paint($user, 7, 7)->assertOk();
        $this->assertSame(0, $user->fresh()->pixel_credits);

        $this->paint($user, 8, 8)->assertStatus(429);

        $rich = User::factory()->create(['pixel_credits' => 500]);
        $this->actingAs($rich)->getJson('/api/pixels/status')->assertJsonPath('credits', 500);
    }

    public function test_guest_limit_is_not_applied_to_logged_in_users(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->postJson('/api/pixels', ['x' => $i, 'y' => 1, 'color' => '#ff0000'])->assertOk();
        }
        $this->postJson('/api/pixels', ['x' => 70, 'y' => 1, 'color' => '#ff0000'])->assertStatus(429);

        $this->paint(User::factory()->create(), 3, 3)->assertOk();
    }
}
