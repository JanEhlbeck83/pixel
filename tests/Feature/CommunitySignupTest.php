<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\User;
use App\Services\Community\ProjectLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunitySignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->travelTo(now()->startOfSecond());
    }

    private function members(int $count = 8): array
    {
        $group = CommunityGroup::firstOrFail();
        $users = User::factory()->count($count)->create();
        foreach ($users as $user) {
            CommunityGroupMember::create(['community_group_id' => $group->id, 'user_id' => $user->id]);
        }

        return [$group, $users];
    }

    private function createProject(CommunityGroup $group, User $leader, array $data = []): CommunityProject
    {
        $this->actingAs($leader)->post('/de/community/groups/'.$group->id.'/projects', $data + [
            'title' => 'Our new artwork', 'type' => 'free', 'width' => 32, 'height' => 32,
            'minimum_participants' => 3, 'expected_duration_hours' => 24,
        ])->assertRedirect('/de/community/groups/'.$group->id);

        return CommunityProject::latest('id')->firstOrFail();
    }

    private function startProject(CommunityGroup $group, $users, array $data = []): CommunityProject
    {
        $project = $this->createProject($group, $users[0], $data);
        foreach ([$users[1], $users[2]] as $user) {
            $this->actingAs($user)->post('/de/community/projects/'.$project->id.'/join')->assertRedirect();
        }

        return $project->fresh();
    }

    public function test_creation_sets_fixed_format_signup_deadline_target_and_estimate_and_leader_counts(): void
    {
        [$group, $users] = $this->members();
        $project = $this->createProject($group, $users[0], ['minimum_participants' => 5, 'expected_duration_hours' => 48]);
        $this->assertSame('recruiting', $project->status);
        $this->assertSame(1, $project->participantCount());
        $this->assertTrue($project->recruitment_close_at->equalTo(now()->addHours(24)));
        $this->assertSame(5, $project->minimum_participants);
        $this->assertSame(48, $project->expected_duration_hours);
        $this->assertNull($project->started_at);
        $html = $this->actingAs($users[1])->get('/de/community/groups/'.$group->id)->assertOk();
        $html->assertSee('Für Projekt anmelden')->assertSee('24 Stunden')->assertDontSee('Stimme abgeben');
    }

    public function test_enough_signups_start_immediately_without_voting_and_estimate_does_not_stop_painting(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users, ['expected_duration_hours' => 1]);
        $this->assertSame('active', $project->status);
        $this->assertTrue($project->started_at->equalTo(now()));
        $this->assertTrue($project->project_close_at->equalTo(now()->addDays(7)));
        $this->actingAs($users[0])->postJson('/de/community/projects/'.$project->id.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        $this->travel(2)->hours();
        $this->postJson('/de/community/projects/'.$project->id.'/pixels', ['x' => 0, 'y' => 0, 'color' => '#123456'])
            ->assertOk()->assertJsonPath('quota.free', 49);
        $this->assertSame(0, DB::table('pixels')->count());
    }

    public function test_target_can_be_higher_than_three_and_no_start_happens_before_target(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users, ['minimum_participants' => 4]);
        $this->assertSame('recruiting', $project->status);
        $this->actingAs($users[3])->post('/de/community/projects/'.$project->id.'/join')->assertRedirect();
        $this->assertSame('active', $project->fresh()->status);
    }

    public function test_scheduled_start_waits_and_late_scheduler_preserves_start_and_seven_day_deadline(): void
    {
        [$group, $users] = $this->members();
        $at = now()->addDays(2);
        $project = $this->startProject($group, $users, ['scheduled_start_at' => $at->toIso8601String()]);
        $this->assertSame('scheduled', $project->status);
        $this->assertNull($project->started_at);
        $this->actingAs($users[0])->post('/de/community/projects/'.$project->id.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        $this->getJson('/api/community/reservations?x=0&y=0&width=300&height=300')
            ->assertOk()->assertJsonCount(1, 'reservations');
        $this->actingAs($users[1])->postJson('/de/community/projects/'.$project->id.'/pixels', ['x' => 0, 'y' => 0, 'color' => '#123456'])->assertConflict();
        $this->assertSame(50, $users[1]->fresh()->free_pixels);
        $this->travel(49)->hours();
        $this->artisan('community:advance-projects')->assertSuccessful();
        $project->refresh();
        $this->assertSame('active', $project->status);
        $this->assertTrue($project->started_at->equalTo($at));
        $this->assertTrue($project->project_close_at->equalTo($at->copy()->addDays(7)));
    }

    public function test_signup_expires_at_exact_twenty_four_hours_and_frees_member_without_cooldown(): void
    {
        [$group, $users] = $this->members();
        $project = $this->createProject($group, $users[0]);
        $this->actingAs($users[1])->post('/de/community/projects/'.$project->id.'/join')->assertRedirect();
        $this->travel(24)->hours();
        $this->actingAs($users[2])->postJson('/de/community/projects/'.$project->id.'/join')->assertConflict();
        $this->assertSame('cancelled', $project->fresh()->status);
        $this->assertSame('recruitment_expired', $project->fresh()->cancellation_reason);
        $new = $this->createProject($group, $users[0]);
        $this->assertNotSame($project->id, $new->id);
    }

    public function test_leading_and_joining_are_mutually_exclusive_in_both_directions_and_duplicate_signup_is_idempotent(): void
    {
        [$group, $users] = $this->members();
        $first = $this->createProject($group, $users[0]);
        $second = $this->createProject($group, $users[1]);
        $this->actingAs($users[0])->postJson('/de/community/projects/'.$second->id.'/join')->assertUnprocessable();
        $this->actingAs($users[2])->post('/de/community/projects/'.$first->id.'/join')->assertRedirect();
        $this->post('/de/community/projects/'.$first->id.'/join')->assertRedirect();
        $this->assertSame(2, $first->participantCount());
        $this->postJson('/de/community/projects/'.$second->id.'/join')->assertUnprocessable();
        $this->postJson('/de/community/groups/'.$group->id.'/projects', [
            'title' => 'Not allowed', 'type' => 'free', 'width' => 32, 'height' => 32,
            'minimum_participants' => 3, 'expected_duration_hours' => 24,
        ])->assertUnprocessable();
    }

    public function test_only_leader_reserves_and_publishes_with_confirmation_and_other_accounts_cannot_see_private_pixels(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users);
        $base = '/de/community/projects/'.$project->id;
        $this->actingAs($users[1])->postJson($base.'/reserve', ['x' => 100, 'y' => 100])->assertForbidden();
        $this->actingAs($users[0])->post($base.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        $this->postJson($base.'/pixels', ['x' => 0, 'y' => 0, 'color' => '#123456'])->assertOk();
        $this->actingAs($users[3])->getJson($base.'/pixels')->assertForbidden();
        $this->actingAs($users[1])->postJson($base.'/complete', ['confirm_publish' => 1])->assertForbidden();
        $this->actingAs($users[0])->postJson($base.'/complete')->assertUnprocessable();
        $this->post($base.'/complete', ['confirm_publish' => 1])->assertRedirect();
        $project->refresh();
        $this->assertSame('published', $project->status);
        $this->assertSame('#123456', DB::table('pixels')->where('x', 100)->where('y', 100)->value('color'));
        $this->assertSame('#ffffff', DB::table('pixels')->where('x', 101)->where('y', 100)->value('color'));
        $this->get($base.'/image')->assertOk()->assertHeader('content-type', 'image/png');
        $this->postJson($base.'/pixels', ['x' => 1, 'y' => 0, 'color' => '#123456'])->assertConflict();
    }

    public function test_seven_day_expiry_retains_pixels_chat_and_releases_reservation(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users);
        $base = '/de/community/projects/'.$project->id;
        $this->actingAs($users[0])->post($base.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        $this->postJson($base.'/pixels', ['x' => 0, 'y' => 0, 'color' => '#123456'])->assertOk();
        $this->postJson($base.'/chat', ['message' => 'Preserve this'])->assertCreated();
        $this->travel(7)->days();
        $this->postJson($base.'/complete', ['confirm_publish' => 1])->assertConflict();
        $this->assertSame('completion_expired', $project->fresh()->cancellation_reason);
        $this->assertSame(1, $project->pixels()->count());
        $this->getJson($base.'/chat')->assertOk()->assertJsonCount(1, 'messages');
        $this->postJson($base.'/chat', ['message' => 'Too late'])->assertConflict();
        $this->getJson('/api/community/reservations?x=0&y=0&width=300&height=300')->assertOk()->assertJsonCount(0, 'reservations');
        $this->assertSame(0, DB::table('pixels')->count());
    }

    public function test_reservation_before_start_blocks_wall_pixels_glyphs_and_other_projects(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users, ['scheduled_start_at' => now()->addDay()->toIso8601String()]);
        $this->actingAs($users[0])->post('/de/community/projects/'.$project->id.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        $this->getJson('/api/community/reservations?x=0&y=0&width=300&height=300')
            ->assertOk()->assertJsonCount(1, 'reservations');
        $this->actingAs($users[3])->postJson('/api/pixels/lock', ['x' => 100, 'y' => 100, 'width' => 1, 'height' => 1])->assertStatus(423);
        $this->postJson('/api/pixels', ['x' => 100, 'y' => 100, 'color' => '#123456'])->assertStatus(423);
        $other = $this->startProject($group, $users->slice(3)->values());
        $this->actingAs($users[3])->postJson('/de/community/projects/'.$other->id.'/reserve', ['x' => 100, 'y' => 100])->assertUnprocessable();
    }

    public function test_upload_validates_before_creating_and_reference_crop_stays_private_and_leader_only(): void
    {
        [$group, $users] = $this->members();
        $data = ['title' => 'Image project', 'type' => 'template', 'width' => 32, 'height' => 32,
            'minimum_participants' => 3, 'expected_duration_hours' => 48];
        $this->actingAs($users[0])->postJson('/de/community/groups/'.$group->id.'/projects',
            $data + ['image' => UploadedFile::fake()->image('small.png', 16, 16)])->assertUnprocessable();
        $this->assertSame(0, CommunityProject::count());
        $project = $this->createProject($group, $users[0], $data + ['image' => UploadedFile::fake()->image('reference.png', 64, 64)]);
        $base = '/de/community/projects/'.$project->id;
        $this->get($base.'/template')->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs(User::factory()->create())->get($base.'/template')->assertForbidden();
        $this->actingAs($users[1])->patchJson($base.'/template', ['crop_x' => 1, 'crop_y' => 1])->assertForbidden();
        $this->actingAs($users[0])->patch($base.'/template', ['crop_x' => 32, 'crop_y' => 32])->assertRedirect();
        $this->patchJson($base.'/template', ['crop_x' => 33, 'crop_y' => 0])->assertUnprocessable();
    }

    public function test_old_voting_actions_are_removed_and_group_view_keeps_mobile_editor_controls(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users);
        $this->actingAs($users[0])->post('/de/community/projects/'.$project->id.'/reserve', ['x' => 100, 'y' => 100])->assertRedirect();
        foreach (['advance', 'abstain', 'proposals', 'reservation-extension'] as $path) {
            $this->postJson('/de/community/projects/'.$project->id.'/'.$path)->assertNotFound();
        }
        $this->get('/de/community/projects/'.$project->id)->assertOk()
            ->assertSee('data-paint-mode', false)->assertSee('data-paint-pick', false)
            ->assertSee('Abschließen und veröffentlichen')->assertDontSee('Stimme abgeben');
    }

    public function test_leader_leaving_cancels_without_deleting_work_and_frees_others(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users);
        $this->actingAs($users[0])->delete('/de/community/group-membership')->assertRedirect();
        $this->assertSame('cancelled', $project->fresh()->status);
        $this->assertFalse(CommunityProject::occupiedBy($users[1]->id));
        $this->assertSame(2, $project->participants()->count());
    }

    public function test_creation_rejects_invalid_minimum_duration_and_start_and_caps_group_at_five(): void
    {
        [$group, $users] = $this->members();
        $base = ['title' => 'Invalid project', 'type' => 'free', 'width' => 32, 'height' => 32,
            'minimum_participants' => 3, 'expected_duration_hours' => 24];
        foreach ([
            ['minimum_participants' => 2], ['minimum_participants' => 1001],
            ['expected_duration_hours' => 0], ['expected_duration_hours' => 169],
            ['scheduled_start_at' => now()->subMinute()->toIso8601String()],
            ['scheduled_start_at' => now()->addDays(8)->toIso8601String()],
        ] as $invalid) {
            $this->actingAs($users[0])->postJson('/de/community/groups/'.$group->id.'/projects', array_replace($base, $invalid))->assertUnprocessable();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->createProject($group, $users[$i]);
        }
        $this->actingAs($users[5])->postJson('/de/community/groups/'.$group->id.'/projects', $base)->assertUnprocessable();
        $this->assertSame(5, CommunityProject::count());
    }

    public function test_legacy_migration_archives_old_phases_and_preserves_pixels_and_start_of_active_work(): void
    {
        [$group, $users] = $this->members();
        $migration = require database_path('migrations/2026_11_07_000001_replace_project_voting_with_scheduled_signup.php');
        $migration->down();
        $oldStart = now()->subDays(2);
        $id = DB::table('community_projects')->insertGetId([
            'community_group_id' => $group->id, 'created_by' => $users[0]->id,
            'title' => 'Legacy active work', 'type' => 'free', 'status' => 'active',
            'width' => 32, 'height' => 32, 'reserved_x' => 100, 'reserved_y' => 100,
            'proposals_close_at' => now(),
            'project_close_at' => $oldStart->copy()->addDays(14), 'created_at' => $oldStart, 'updated_at' => now(),
        ]);
        DB::table('community_project_pixels')->insert([
            'community_project_id' => $id, 'user_id' => $users[0]->id, 'x' => 0, 'y' => 0,
            'color' => '#123456', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $oldId = DB::table('community_projects')->insertGetId([
            'community_group_id' => $group->id, 'created_by' => $users[1]->id,
            'title' => 'Legacy vote', 'type' => 'free', 'status' => 'voting',
            'width' => 32, 'height' => 32, 'created_at' => now(), 'updated_at' => now(),
            'proposals_close_at' => now(),
        ]);
        $migration->up();
        $active = CommunityProject::findOrFail($id);
        $this->assertTrue($active->started_at->equalTo($oldStart));
        $this->assertTrue($active->project_close_at->equalTo($oldStart->copy()->addDays(7)));
        $this->assertSame('#123456', $active->pixels()->value('color'));
        $this->assertSame('archived', CommunityProject::findOrFail($oldId)->status);
    }

    public function test_loss_of_a_member_before_scheduled_start_expires_instead_of_starting_understaffed(): void
    {
        [$group, $users] = $this->members();
        $project = $this->startProject($group, $users, ['scheduled_start_at' => now()->addHour()->toIso8601String()]);
        $this->actingAs($users[1])->delete('/de/community/group-membership')->assertRedirect();
        $this->travel(1)->hours();
        $updated = app(ProjectLifecycle::class)->refresh($project);
        $this->assertSame('cancelled', $updated->status);
        $this->assertSame('insufficient_participants', $updated->cancellation_reason);
        $this->assertNull($updated->started_at);
    }

    public function test_a_small_group_can_recruit_new_members_after_project_creation(): void
    {
        [$group, $users] = $this->members(1);
        $project = $this->createProject($group, $users[0]);
        $newcomers = User::factory()->count(2)->create();
        foreach ($newcomers as $user) {
            CommunityGroupMember::create(['community_group_id' => $group->id, 'user_id' => $user->id]);
            $this->actingAs($user)->post('/de/community/projects/'.$project->id.'/join')->assertRedirect();
        }
        $this->assertSame('active', $project->fresh()->status);
    }
}
