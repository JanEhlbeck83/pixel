<?php

namespace Tests\Feature;

use App\Models\Pixel;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentReportTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + ['x' => 10, 'y' => 10, 'width' => 20, 'height' => 20, 'reason' => 'hate', 'message' => 'Test'];
    }

    public function test_guest_can_report_an_area_and_a_snapshot_is_stored(): void
    {
        $this->postJson('/report', $this->payload(['email' => 'melder@example.com']))->assertCreated();

        $report = Report::first();
        $this->assertSame('open', $report->status);
        $this->assertSame('melder@example.com', $report->reporter_email);
        $this->assertFileExists($report->snapshotPath());
        File::delete($report->snapshotPath());
    }

    public function test_report_area_is_limited_and_validated(): void
    {
        $this->postJson('/report', $this->payload(['width' => 301]))->assertStatus(422);
        $this->postJson('/report', $this->payload(['reason' => 'nope']))->assertStatus(422);
        $this->postJson('/report', $this->payload(['x' => 999999]))->assertStatus(422);
    }

    public function test_only_verified_admin_emails_reach_the_admin_page(): void
    {
        config(['wall.admin_emails' => ['admin@example.com']]);

        $this->get('/admin/reports')->assertRedirect('/login');

        $user = User::factory()->create(['email' => 'user@example.com']);
        $this->actingAs($user)->get('/admin/reports')->assertNotFound();

        $unverified = User::factory()->unverified()->create(['email' => 'admin@example.com']);
        $this->actingAs($unverified)->get('/admin/reports')->assertNotFound();

        $admin = User::factory()->create(['email' => 'Admin@Example.com']);
        $this->actingAs($admin)->get('/admin/reports')->assertOk();
    }

    public function test_reset_clears_area_resolves_overlapping_reports_and_keeps_other_pixels(): void
    {
        config(['wall.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $author = User::factory()->create();

        Pixel::create(['x' => 12, 'y' => 12, 'color' => '#ff0000', 'updated_by_user_id' => $author->id]);
        Pixel::create(['x' => 500, 'y' => 500, 'color' => '#00ff00']);

        $this->postJson('/report', $this->payload())->assertCreated();
        $this->postJson('/report', $this->payload(['x' => 15, 'y' => 15]))->assertCreated();
        $report = Report::orderBy('id')->first();

        $this->actingAs($admin)->post("/admin/reports/{$report->id}/reset")->assertRedirect();

        $this->assertSame('#ffffff', Pixel::where('x', 12)->first()->color);
        $this->assertSame('#00ff00', Pixel::where('x', 500)->first()->color);
        $this->assertSame(0, Report::where('status', 'open')->count());
        $this->assertFalse($author->fresh()->isBanned());

        Report::all()->each(fn ($r) => File::delete($r->snapshotPath()));
    }

    public function test_reset_and_ban_blocks_authors_from_placing_pixels(): void
    {
        config(['wall.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $author = User::factory()->create();
        Pixel::create(['x' => 12, 'y' => 12, 'color' => '#ff0000', 'updated_by_user_id' => $author->id]);

        $this->postJson('/report', $this->payload())->assertCreated();
        $report = Report::first();

        $this->actingAs($admin)->post("/admin/reports/{$report->id}/reset-ban")->assertRedirect();

        $this->assertTrue($author->fresh()->isBanned());
        $this->assertFalse($admin->fresh()->isBanned());

        $this->actingAs($author->fresh())
            ->postJson('/api/pixels', ['x' => 1, 'y' => 1, 'color' => '#123456'])
            ->assertForbidden();

        File::delete($report->snapshotPath());
    }

    public function test_dismiss_only_marks_the_report(): void
    {
        config(['wall.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        Pixel::create(['x' => 12, 'y' => 12, 'color' => '#ff0000']);

        $this->postJson('/report', $this->payload())->assertCreated();
        $report = Report::first();

        $this->actingAs($admin)->post("/admin/reports/{$report->id}/dismiss")->assertRedirect();

        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertSame('#ff0000', Pixel::where('x', 12)->first()->color);

        File::delete($report->snapshotPath());
    }
}
