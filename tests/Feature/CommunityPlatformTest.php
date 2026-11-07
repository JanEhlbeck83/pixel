<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\User;
use Database\Seeders\CommunityStarterGroupsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CommunityPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_groups_are_listed_with_their_emblems(): void
    {
        $this->seed(CommunityStarterGroupsSeeder::class);

        $this->get(route('community.index'))
            ->assertOk()
            ->assertSee('Retro Riot')
            ->assertSee('Cozy Pixels')
            ->assertSee('Neon District')
            ->assertSee('Anime Atelier')
            ->assertSee('retro-riot.svg')
            ->assertSee('cozy-pixels.svg')
            ->assertSee('neon-district.svg')
            ->assertSee('anime-atelier.svg')
            ->assertSee(__('Projekt-Showcase'))
            ->assertSee(__('Beispielprojekt'))
            ->assertSee('pixel-sunset.svg')
            ->assertSee('arcade-hero.svg')
            ->assertSee('neon-nights.svg')
            ->assertSee(__('Gruppen'))
            ->assertDontSee(__('Community-Gruppen'));

        $this->assertDatabaseCount('community_groups', 4);
        $this->assertDatabaseHas('community_groups', ['name' => 'Retro Riot', 'status' => 'approved', 'founder_id' => null]);
        $this->assertDatabaseHas('community_groups', ['name' => 'Anime Atelier', 'status' => 'approved', 'founder_id' => null]);
    }

    public function test_starter_group_seeding_preserves_existing_groups(): void
    {
        $group = CommunityGroup::where('emblem', 'retro-riot')->firstOrFail();
        $group->update(['description' => 'Updated community description.']);
        $original = $group->fresh()->getAttributes();

        $this->travel(1)->days();
        $this->seed(CommunityStarterGroupsSeeder::class);
        $this->seed(CommunityStarterGroupsSeeder::class);

        $this->assertSame($original, $group->fresh()->getAttributes());
        $this->assertDatabaseCount('community_groups', 4);
    }

    public function test_migrations_create_approved_starter_groups(): void
    {
        $this->assertDatabaseCount('community_groups', 4);

        foreach (['retro-riot', 'cozy-pixels', 'neon-district', 'anime-atelier'] as $emblem) {
            $this->assertDatabaseHas('community_groups', [
                'emblem' => $emblem,
                'status' => 'approved',
                'founder_id' => null,
            ]);
            $this->assertFileExists(public_path('images/community/emblems/'.$emblem.'.svg'));
        }
    }

    public function test_group_creation_is_one_time_and_requires_admin_approval(): void
    {
        $founder = User::factory()->create();
        $this->actingAs($founder)->post(route('community.groups.store'), [
            'name' => 'Pixel Pioneers',
            'description' => 'We create a shared pixel-art project together.',
        ])->assertRedirect();

        $group = CommunityGroup::where('founder_id', $founder->id)->firstOrFail();
        $this->assertSame('pending', $group->status);
        $this->assertDatabaseMissing('community_group_members', ['user_id' => $founder->id]);
        $this->get(route('community.index'))->assertDontSee('Pixel Pioneers');

        $this->actingAs($founder)->post(route('community.groups.store'), [
            'name' => 'Another Group',
            'description' => 'This should not be allowed as a second group.',
        ])->assertSessionHasErrors('group');

        Config::set('wall.admin_emails', ['moderator@example.com']);
        $admin = User::factory()->create(['email' => 'moderator@example.com']);
        $this->actingAs($admin)->post(route('admin.community-groups.approve', $group))->assertRedirect();

        $this->assertSame('approved', $group->fresh()->status);
        $this->assertDatabaseHas('community_group_members', [
            'community_group_id' => $group->id,
            'user_id' => $founder->id,
            'role' => 'founder',
        ]);
    }

    public function test_join_status_appears_above_own_group_and_leave_status_stays_in_explanation_panel(): void
    {
        $user = User::factory()->create();
        $group = CommunityGroup::where('emblem', 'retro-riot')->firstOrFail();

        $this->actingAs($user)->from(route('community.index'))
            ->post(route('community.groups.join', $group), [
                'confirm_group_switch_cooldown' => '1',
            ])
            ->assertRedirect(route('community.index'))
            ->assertSessionHas('status', __('Du bist der Gruppe beigetreten.'));

        $joined = $this->get(route('community.index'))
            ->assertOk()
            ->assertSeeInOrder([
                __('Du bist der Gruppe beigetreten.'),
                'class="panel community-my-group"',
                'class="panel community-panel"',
            ], false);
        $this->assertSame(1, substr_count($joined->getContent(), 'class="community-status"'));

        $this->delete(route('community.groups.leave'))
            ->assertRedirect(route('community.index'))
            ->assertSessionHas('status', __('Du hast die Gruppe verlassen.'));

        $left = $this->get(route('community.index'))
            ->assertOk()
            ->assertDontSee('class="panel community-my-group"', false)
            ->assertSeeInOrder([
                'class="panel community-panel"',
                'id="communityExplanationContent"',
                __('Du hast die Gruppe verlassen.'),
            ], false);
        $this->assertSame(1, substr_count($left->getContent(), 'class="community-status"'));
    }

    public function test_members_can_only_belong_to_one_group(): void
    {
        $user = User::factory()->create();
        $first = CommunityGroup::where('emblem', 'retro-riot')->firstOrFail();
        $second = CommunityGroup::create([
            'founder_id' => User::factory()->create()->id,
            'name' => 'Second Group',
            'description' => 'Second group description.',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($user)->get(route('community.index'))
            ->assertOk()
            ->assertSee(__('Nach einem freiwilligen Austritt kannst du 7 Tage lang keiner anderen Gruppe beitreten. In deine zuletzt verlassene Gruppe kannst du sofort zurückkehren.'))
            ->assertSee(route('community.groups.join', $first), false)
            ->assertSee('name="confirm_group_switch_cooldown"', false)
            ->assertSee('required', false);

        $this->actingAs($user)->post(route('community.groups.join', $first))
            ->assertSessionHasErrors('confirm_group_switch_cooldown');
        $this->actingAs($user)->post(route('community.groups.join', $first), [
            'confirm_group_switch_cooldown' => '1',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('community.groups.join', $second), [
            'confirm_group_switch_cooldown' => '1',
        ])->assertSessionHasErrors('group');
        $this->assertDatabaseCount('community_group_members', 1);
        $communityResponse = $this->actingAs($user)->get(route('community.index'))
            ->assertOk()
            ->assertSee(__('Deine Gruppe'))
            ->assertSee($first->name)
            ->assertSee('retro-riot.svg')
            ->assertSee(__('In den letzten 5 Minuten aktiv'))
            ->assertSee(__('Zuletzt in der Gruppe aktiv: :time', ['time' => '']))
            ->assertSee(__('Weitere Gruppen'))
            ->assertSee($second->name)
            ->assertDontSee('community-card--current')
            ->assertSee(__('Gruppe verlassen'));
        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('Deine Gruppe'))
            ->assertSee($first->name)
            ->assertSee('retro-riot.svg')
            ->assertSee(__('Gruppe verlassen'));

        $this->actingAs($user)->delete(route('community.groups.leave'))->assertRedirect();
        $this->assertSame($first->id, $user->fresh()->last_left_community_group_id);
        $this->assertNotNull($user->fresh()->community_group_left_at);

        $this->actingAs($user)->post(route('community.groups.join', $second), [
            'confirm_group_switch_cooldown' => '1',
        ])->assertSessionHasErrors('group');
        $this->actingAs($user)->post(route('community.groups.join', $first), [
            'confirm_group_switch_cooldown' => '1',
        ])->assertRedirect();

        $this->actingAs($user)->delete(route('community.groups.leave'))->assertRedirect();
        $this->travel(7)->days();
        $this->actingAs($user)->post(route('community.groups.join', $second), [
            'confirm_group_switch_cooldown' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('community_group_members', [
            'community_group_id' => $second->id,
            'user_id' => $user->id,
        ]);
    }
}
