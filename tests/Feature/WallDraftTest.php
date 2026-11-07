<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityProject;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\User;
use App\Models\WallArtwork;
use App\Models\WallDraft;
use App\Services\Wall\PixelQuota;
use App\Services\Wall\WallDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class WallDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
    }

    private function draft(User $user, array $attributes = []): WallDraft
    {
        return WallDraft::create($attributes + ['user_id' => $user->id, 'pixels' => [], 'paid_cells' => []]);
    }

    private function edit(WallDraft $draft, array $changes, array $extra = [])
    {
        return $this->patchJson("/api/wall-drafts/{$draft->id}", $extra + [
            'version' => $draft->fresh()->version, 'title' => '', 'changes' => $changes,
        ]);
    }

    private function publish(WallDraft $draft, array $extra = [])
    {
        return $this->postJson("/api/wall-drafts/{$draft->id}/publish", $extra + [
            'version' => $draft->fresh()->version, 'x' => 80, 'y' => 100,
        ]);
    }

    public function test_account_draft_survives_reload_and_each_cell_is_paid_exactly_once(): void
    {
        $user = User::factory()->create(['free_pixels' => 50]);
        $this->actingAs($user);
        $id = $this->getJson('/api/wall-drafts')->assertOk()->json('drafts.0.id');
        $draft = WallDraft::findOrFail($id);
        $this->edit($draft, [['index' => 0, 'color' => '#ABCDEF'], ['index' => 999, 'color' => '#000000']])
            ->assertOk()->assertJsonPath('cost', 2)->assertJsonPath('quota.free', 48);
        $this->edit($draft, [['index' => 0, 'color' => null]])->assertOk()->assertJsonPath('cost', 0);
        $this->edit($draft, [['index' => 0, 'color' => '#123456']])->assertOk()->assertJsonPath('cost', 0);
        $this->getJson('/api/wall-drafts')->assertOk()->assertJsonPath('drafts.0.pixels.0', '#123456');
        $this->assertSame(48, $user->fresh()->free_pixels);
        $this->assertCount(2, $draft->fresh()->paid_cells);
        $this->assertSame(1, WallDraft::where('user_id', $user->id)->count());
    }

    public function test_overspending_rejects_entire_stroke_without_charging_and_uses_credits_after_free(): void
    {
        $user = User::factory()->create(['free_pixels' => 1, 'free_pixels_at' => now(), 'pixel_credits' => 1]);
        $draft = $this->draft($user);
        $this->actingAs($user);
        $points = collect(range(0, 2))->map(fn ($index) => ['index' => $index, 'color' => '#123456'])->all();
        $this->edit($draft, $points)->assertStatus(429)->assertJsonPath('quota.free', 1);
        $this->assertSame([], $draft->fresh()->pixels);
        $this->assertSame(1, $user->fresh()->pixel_credits);
        $this->edit($draft, array_slice($points, 0, 2))->assertOk()->assertJsonPath('quota.free', 0)->assertJsonPath('quota.credits', 0);
        $this->edit($draft, [['index' => 0, 'color' => '#ffffff']])->assertOk()->assertJsonPath('cost', 0);
    }

    public function test_versions_prevent_double_charges_or_lost_updates_and_payload_is_bounded(): void
    {
        $user = User::factory()->create();
        $draft = $this->draft($user);
        $this->actingAs($user);
        $this->edit($draft, [['index' => 1, 'color' => '#123456']])->assertOk();
        $this->edit($draft, [['index' => 2, 'color' => '#123456']], ['version' => 0])->assertStatus(409)->assertJsonPath('draft.version', 1);
        foreach ([
            [['index' => 1000, 'color' => '#123456']],
            [['index' => -1, 'color' => '#123456']],
            [['index' => 1, 'color' => 'transparent']],
            [['index' => 1, 'color' => '#123456', 'paid' => true]],
            [['index' => 1, 'color' => '#123456'], ['index' => 1, 'color' => '#123456']],
        ] as $changes) {
            $this->edit($draft, $changes)->assertUnprocessable();
        }
        $this->assertSame(49, $user->fresh()->free_pixels);
    }

    public function test_guest_uses_shared_ip_quota_session_cookie_and_cannot_access_another_draft(): void
    {
        $response = $this->getJson('/api/wall-drafts')->assertOk();
        $this->assertSame(0, $response->getCookie(WallDrafts::COOKIE)->getExpiresTime());
        $token = str_repeat('a', 64);
        $draft = WallDraft::create(['guest_hash' => hash('sha256', $token), 'pixels' => [], 'paid_cells' => [], 'expires_at' => now()->addDay()]);
        $this->withCredentials()->withCookie(WallDrafts::COOKIE, $token);
        $this->edit($draft, [['index' => 0, 'color' => '#123456']])->assertOk()->assertJsonPath('quota.free', 49);
        $this->getJson('/api/pixels/status')->assertOk()->assertJsonPath('free', 49);
        $this->withCookie(WallDrafts::COOKIE, str_repeat('b', 64));
        $this->edit($draft, [])->assertNotFound();
        $this->publish($draft)->assertNotFound();
    }

    public function test_registration_claims_paid_guest_work_and_new_account_starts_at_fifty(): void
    {
        Notification::fake();
        $token = str_repeat('c', 64);
        $draft = WallDraft::create([
            'guest_hash' => hash('sha256', $token), 'pixels' => ['0' => '#123456'], 'paid_cells' => [0], 'expires_at' => now()->addDay(),
        ]);
        $this->withCookie(WallDrafts::COOKIE, $token)->post('/de/register', [
            'name' => 'Draft Artist', 'email' => 'draft@example.test', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect();
        $user = User::where('email', 'draft@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, $draft->fresh()->user_id);
        $this->assertNull($draft->fresh()->guest_hash);
        $this->assertSame([0], $draft->fresh()->paid_cells);
        $this->getJson('/api/pixels/status')->assertOk()->assertJsonPath('free', 50);
        $this->edit($draft, [['index' => 0, 'color' => '#000000']])->assertOk()->assertJsonPath('cost', 0)->assertJsonPath('quota.free', 50);
    }

    public function test_login_import_keeps_existing_draft_without_a_repeat_bonus(): void
    {
        $user = User::factory()->create(['free_pixels' => 3, 'free_pixels_at' => now()]);
        $existing = $this->draft($user, ['pixels' => [1 => '#ffffff'], 'paid_cells' => [1]]);
        $token = str_repeat('d', 64);
        $guest = WallDraft::create([
            'guest_hash' => hash('sha256', $token), 'pixels' => [0 => '#123456'], 'paid_cells' => [0], 'expires_at' => now()->addDay(),
        ]);
        $this->withCookie(WallDrafts::COOKIE, $token)->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertSame($user->id, $guest->fresh()->user_id);
        $this->assertSame([1 => '#ffffff'], $existing->fresh()->pixels);
        $this->getJson('/api/wall-drafts')->assertOk()->assertJsonCount(2, 'drafts');
        $this->assertSame(3, $user->fresh()->free_pixels);
    }

    public function test_social_registration_also_transfers_the_guest_draft_without_recharging(): void
    {
        $token = str_repeat('e', 64);
        $draft = WallDraft::create([
            'guest_hash' => hash('sha256', $token), 'pixels' => [999 => '#123456'], 'paid_cells' => [999], 'expires_at' => now()->addDay(),
        ]);
        $social = (new SocialUser)->map(['id' => 'draft-social', 'name' => 'Social Artist', 'email' => 'draft-social@example.test']);
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($social);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $this->withCookie(WallDrafts::COOKIE, $token)->get('/auth/google/callback')->assertRedirect();
        $user = User::where('email', 'draft-social@example.test')->firstOrFail();
        $this->assertSame($user->id, $draft->fresh()->user_id);
        $this->getJson('/api/pixels/status')->assertOk()->assertJsonPath('free', 50);
    }

    public function test_guest_can_publish_paid_work_at_zero_quota_but_cannot_reuse_it(): void
    {
        $token = str_repeat('f', 64);
        $draft = WallDraft::create([
            'guest_hash' => hash('sha256', $token), 'pixels' => [999 => '#123456'], 'paid_cells' => [999], 'expires_at' => now()->addDay(),
        ]);
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $quota = app(PixelQuota::class);
        $quota->recordGuestPixel($request, 50);
        $this->withCredentials()->withCookie(WallDrafts::COOKIE, $token);
        $this->publish($draft)->assertOk()->assertJsonPath('quota.free', 0);
        $this->assertSame(0, WallArtwork::count());
        $this->publish($draft)->assertUnprocessable();
        $this->assertSame(50, $quota->guestUsed($request));
    }

    public function test_expired_guest_cookie_is_cleared_and_draft_cannot_be_claimed(): void
    {
        $token = str_repeat('9', 64);
        $draft = WallDraft::create([
            'guest_hash' => hash('sha256', $token), 'pixels' => [0 => '#123456'], 'paid_cells' => [0], 'expires_at' => now()->subMinute(),
        ]);
        $this->withCredentials()->withCookie(WallDrafts::COOKIE, $token);
        $this->getJson('/api/wall-drafts')->assertStatus(410)->assertCookieExpired(WallDrafts::COOKIE);
        $this->edit($draft, [])->assertNotFound();
        $this->publish($draft)->assertNotFound();
    }

    public function test_database_failure_while_saving_refunds_the_transactional_quota_charge(): void
    {
        $user = User::factory()->create(['free_pixels' => 50]);
        $draft = $this->draft($user);
        DB::unprepared("CREATE TRIGGER reject_draft BEFORE UPDATE ON wall_drafts BEGIN SELECT RAISE(FAIL, 'test failure'); END");
        $this->actingAs($user);
        $this->edit($draft, [['index' => 0, 'color' => '#123456']])->assertServerError();
        $this->assertSame(50, $user->fresh()->free_pixels);
        $this->assertSame([], $draft->fresh()->pixels);
        $this->assertSame([], $draft->fresh()->paid_cells);
    }

    public function test_publication_is_prepaid_sparse_atomic_archived_private_and_single_use(): void
    {
        $user = User::factory()->create(['free_pixels' => 0, 'free_pixels_at' => now()]);
        $draft = $this->draft($user, ['title' => 'Private art', 'pixels' => [0 => '#123456', 999 => '#abcdef'], 'paid_cells' => [0, 999]]);
        $other = User::factory()->create();
        PixelEditLock::create(['x' => 81, 'y' => 100, 'locked_by_user_id' => $other->id, 'expires_at' => now()->addMinute()]);
        $this->actingAs($user);
        DB::enableQueryLog();
        $this->publish($draft)->assertOk()->assertJsonCount(2, 'pixels')->assertJsonPath('quota.free', 0);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn ($query) => str_starts_with($query['query'], 'insert into "pixels"')));
        $this->assertLessThan(35, count($queries));
        $this->assertDatabaseHas('pixels', ['x' => 99, 'y' => 149, 'color' => '#abcdef']);
        $this->assertDatabaseMissing('pixels', ['x' => 81, 'y' => 100]);
        $artwork = WallArtwork::sole();
        $this->assertFalse($artwork->is_public);
        $this->assertSame('Private art', $artwork->title);
        $this->assertSame([], $draft->fresh()->pixels);
        $this->assertSame([], $draft->fresh()->paid_cells);
        $this->publish($draft, ['version' => 0])->assertStatus(409);
        $this->assertSame(1, WallArtwork::count());
    }

    public function test_a_thousand_cells_are_supported_and_free_placement_uses_one_upsert(): void
    {
        $user = User::factory()->create(['free_pixels' => 50, 'pixel_credits' => 950]);
        $draft = $this->draft($user);
        $this->actingAs($user);
        $changes = collect(range(0, 999))->map(fn ($index) => ['index' => $index, 'color' => '#123456'])->all();
        $this->edit($draft, $changes)->assertOk()->assertJsonPath('cost', 1000)->assertJsonPath('quota.credits', 0);
        $this->publish($draft)->assertOk()->assertJsonCount(1000, 'pixels')->assertJsonPath('quota.free', 0);
        $this->assertSame(1000, Pixel::count());
        $this->assertCount(1000, WallArtwork::sole()->pixels);
    }

    public function test_locked_last_pixel_blocks_everything_without_destroying_draft(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->draft($user, ['pixels' => [0 => '#123456', 999 => '#abcdef'], 'paid_cells' => [0, 999]]);
        PixelEditLock::create(['x' => 99, 'y' => 149, 'locked_by_user_id' => $other->id, 'expires_at' => now()->addMinute()]);
        $this->actingAs($user);
        $this->publish($draft)->assertStatus(423);
        $this->assertSame(0, Pixel::count());
        $this->assertSame(0, WallArtwork::count());
        $this->assertCount(2, $draft->fresh()->pixels);
        PixelEditLock::query()->delete();
        $pixel = new Pixel(['x' => 99, 'y' => 149, 'color' => '#abcdef', 'updated_by_user_id' => $other->id]);
        $pixel->save();
        $this->publish($draft)->assertStatus(423);
        $this->assertDatabaseMissing('pixels', ['x' => 80, 'y' => 100]);
    }

    public function test_reservation_and_bounds_checks_prevent_partial_publication(): void
    {
        $user = User::factory()->create();
        $draft = $this->draft($user, ['pixels' => [0 => '#123456', 999 => '#abcdef'], 'paid_cells' => [0, 999]]);
        $group = CommunityGroup::create(['name' => 'Reserved', 'description' => 'Test group', 'founder_id' => $user->id]);
        $project = CommunityProject::create([
            'community_group_id' => $group->id, 'created_by' => $user->id, 'title' => 'Protected', 'type' => 'small',
            'status' => 'active', 'width' => 10, 'height' => 10, 'reserved_x' => 99, 'reserved_y' => 149,
            'proposals_close_at' => now()->addHour(),
        ]);
        $this->actingAs($user);
        $this->publish($draft)->assertStatus(423);
        $project->delete();
        $this->publish($draft, ['x' => 2147483647])->assertUnprocessable();
        $this->assertSame(0, Pixel::count());
        $this->assertCount(2, $draft->fresh()->pixels);
    }

    public function test_gallery_failure_rolls_back_wall_and_preserves_prepaid_draft(): void
    {
        $user = User::factory()->create();
        $draft = $this->draft($user, ['pixels' => [0 => '#123456'], 'paid_cells' => [0]]);
        DB::unprepared("CREATE TRIGGER reject_artwork BEFORE INSERT ON wall_artworks BEGIN SELECT RAISE(FAIL, 'test failure'); END");
        $this->actingAs($user);
        $this->publish($draft)->assertServerError();
        $this->assertSame(0, Pixel::count());
        $this->assertSame([0 => '#123456'], $draft->fresh()->pixels);
        $this->assertSame(50, $user->fresh()->free_pixels);
    }

    public function test_foreign_drafts_bans_empty_and_expired_guest_are_rejected(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = $this->draft($owner);
        $this->actingAs($other);
        $this->edit($draft, [])->assertNotFound();
        $this->publish($draft)->assertNotFound();
        $this->actingAs($owner);
        $this->publish($draft)->assertUnprocessable();
        $owner->forceFill(['banned_at' => now()])->save();
        $this->edit($draft, [])->assertForbidden();
        $this->publish($draft)->assertForbidden();
    }

    public function test_gallery_privacy_visibility_deletion_and_account_removal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $art = WallArtwork::create(['user_id' => $owner->id, 'title' => 'Secret artwork', 'pixels' => [0 => '#123456'], 'wall_x' => 1, 'wall_y' => 2]);
        $this->get("/de/artists/{$owner->id}/gallery")->assertOk()->assertDontSee('Secret artwork');
        $this->get("/de/artworks/{$art->id}/image")->assertNotFound();
        $this->actingAs($other);
        $this->patch("/de/artworks/{$art->id}", ['is_public' => 1])->assertForbidden();
        $this->delete("/de/artworks/{$art->id}")->assertForbidden();
        $this->actingAs($owner);
        $this->get("/de/artists/{$owner->id}/gallery")->assertOk()->assertSee('Secret artwork')->assertSee('Privat');
        $this->get("/de/artworks/{$art->id}/image")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->patch("/de/artworks/{$art->id}", ['is_public' => 1])->assertRedirect();
        $this->actingAs($other);
        $this->get("/de/artists/{$owner->id}/gallery")->assertOk()->assertSee('Secret artwork');
        $this->get('/de/gallery')->assertOk()->assertSee('Secret artwork');
        $this->get("/de/artworks/{$art->id}/image")->assertOk();
        $this->actingAs($owner);
        $this->patch("/de/artworks/{$art->id}", ['is_public' => 0])->assertRedirect();
        $this->actingAs($other);
        $this->get("/de/artworks/{$art->id}/image")->assertNotFound();
        $this->actingAs($owner);
        $this->delete("/de/artworks/{$art->id}")->assertRedirect();
        $this->assertSame(0, WallArtwork::count());
        $this->draft($owner);
        $owner->delete();
        $this->assertSame(0, WallDraft::count());
    }

    public function test_wall_has_fixed_draft_controls_and_guest_registration_incentive(): void
    {
        $this->get('/de')->assertOk()->assertSee('id="wallDraftToggle"', false)
            ->assertSee('width="20" height="50"', false)->assertSee('bis zu 50 Pixeln')->assertSee('Auf der Wall platzieren');
    }
}
