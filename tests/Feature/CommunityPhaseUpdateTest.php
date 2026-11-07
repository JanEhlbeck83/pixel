<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\CommunityProjectParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPhaseUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_remain_private_localized_and_offer_signup_only_to_free_members(): void
    {
        $group = CommunityGroup::firstOrFail();
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            CommunityGroupMember::create(['community_group_id' => $group->id, 'user_id' => $user->id]);
        }
        $project = CommunityProject::create([
            'community_group_id' => $group->id, 'created_by' => $users[0]->id, 'title' => 'Scheduled artwork',
            'type' => 'free', 'status' => 'scheduled', 'width' => 32, 'height' => 32,
            'proposals_close_at' => now(), 'scheduled_start_at' => now()->addHour(),
        ]);
        foreach (['de' => '/de', 'en' => '', 'es' => '/es'] as $locale => $prefix) {
            $this->actingAs($users[1])->withHeader('X-App-Locale', $locale)->getJson('/api/community/phase-updates')
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('projects.0.status', 'scheduled')
                ->assertJsonPath('projects.0.message', trans('Der Start für „:title“ ist geplant.', ['title' => $project->title], $locale))
                ->assertJsonPath('projects.0.join_url', url($prefix.'/community/projects/'.$project->id).'#projectJoinForm');
        }
        CommunityProjectParticipant::create(['community_project_id' => $project->id, 'user_id' => $users[1]->id]);
        $this->getJson('/api/community/phase-updates')->assertOk()->assertJsonPath('projects.0.join_url', null);
        $this->actingAs($users[0])->getJson('/api/community/phase-updates')->assertOk()->assertJsonPath('projects.0.join_url', null);
        CommunityGroupMember::where('user_id', $users[1]->id)->delete();
        $this->actingAs($users[1])->getJson('/api/community/phase-updates')->assertOk()->assertJsonCount(0, 'projects');
    }

    public function test_updates_require_verified_unbanned_accounts(): void
    {
        $this->getJson('/api/community/phase-updates')->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->getJson('/api/community/phase-updates')->assertForbidden();
        $this->actingAs(User::factory()->create(['banned_at' => now()]))->getJson('/api/community/phase-updates')->assertForbidden();
    }

    public function test_notices_remain_available_on_shared_pages(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/de', '/de/community', '/de/profile', '/de/faq', '/gallery'] as $url) {
            $this->get($url)->assertOk()->assertSee('id="projectPhaseNoticeConfig"', false);
        }
    }
}
