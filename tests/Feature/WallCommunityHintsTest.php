<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\CommunityProjectParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallCommunityHintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_discover_groups_and_members_see_their_own_group(): void
    {
        $this->get('/de')->assertOk()->assertSee('Jetzt Gruppen entdecken')->assertDontSee('data-community-project-hint', false);
        $user = User::factory()->create();
        $group = CommunityGroup::firstOrFail();
        CommunityGroupMember::create(['community_group_id' => $group->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get('/de')->assertOk()->assertSee($group->name)->assertSee('Gruppenhinweis schließen');
    }

    public function test_signup_scheduled_and_active_hints_have_no_voting_and_expired_projects_disappear(): void
    {
        $user = User::factory()->create();
        $group = CommunityGroup::firstOrFail();
        CommunityGroupMember::create(['community_group_id' => $group->id, 'user_id' => $user->id]);
        $project = CommunityProject::create([
            'community_group_id' => $group->id, 'created_by' => $user->id, 'title' => 'My project',
            'type' => 'free', 'status' => 'recruiting', 'width' => 32, 'height' => 32,
            'proposals_close_at' => now(), 'recruitment_close_at' => now()->addDay(),
        ]);
        CommunityProjectParticipant::create(['community_project_id' => $project->id, 'user_id' => $user->id]);
        foreach (['recruiting' => 'Teilnehmende gesucht', 'scheduled' => 'Geplantes Projekt ansehen', 'active' => 'Im Projekt weiterpixeln'] as $phase => $action) {
            $project->update(['status' => $phase, 'scheduled_start_at' => now()->addHour(), 'project_close_at' => now()->addDays(7)]);
            $this->actingAs($user)->get('/de')->assertOk()->assertSee($action)
                ->assertSee('Projekthinweis schließen')->assertDontSee('Jetzt abstimmen');
        }
        $project->update(['project_close_at' => now()]);
        $this->get('/de')->assertOk()->assertDontSee('data-community-project-hint', false);
    }
}
