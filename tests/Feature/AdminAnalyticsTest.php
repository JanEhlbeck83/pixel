<?php

namespace Tests\Feature;

use App\Models\AnalyticsVisit;
use App\Models\User;
use App\Services\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_analytics(): void
    {
        config(['wall.admin_emails' => ['admin@example.com']]);

        $this->get('/admin/analytics')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->get('/admin/analytics')
            ->assertNotFound();

        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->get('/admin/analytics')
            ->assertOk()
            ->assertSee('Daily visits');
    }

    public function test_public_page_creates_daily_anonymous_visit_and_heartbeat_records_active_time(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();

        $this->withHeaders(['User-Agent' => 'PixRiot analytics test'])
            ->get('/')
            ->assertOk();

        $this->assertDatabaseCount('analytics_visits', 1);
        $visit = AnalyticsVisit::firstOrFail();
        $this->assertSame(now()->toDateString(), $visit->visit_date->toDateString());
        $this->assertSame(0, $visit->duration_seconds);
        $this->assertTrue($visit->country_code === null || preg_match('/^[A-Z]{2}$/', $visit->country_code) === 1);
        $sessionId = $this->app['session.store']->getId();
        $expectedHash = hash_hmac('sha256', $sessionId.'|'.now()->toDateString(), (string) config('app.key'));
        $this->assertSame($expectedHash, $visit->visitor_hash);

        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        app(Analytics::class)->recordActiveTime($request, 15);
        app(Analytics::class)->recordPixels($request, 2);

        $this->postJson(route('analytics.heartbeat'), ['seconds' => 31])
            ->assertUnprocessable();
        $this->assertSame(15, $visit->fresh()->duration_seconds);
        $this->assertSame(2, $visit->fresh()->pixels_placed);

        $this->postJson(route('analytics.heartbeat'), ['seconds' => 15])
            ->assertNoContent();
    }

    public function test_bot_requests_are_not_recorded_as_visits(): void
    {
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
            ->get('/')
            ->assertOk();

        $this->assertDatabaseCount('analytics_visits', 0);
    }
}
