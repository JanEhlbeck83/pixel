<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_project_notification_preferences_can_be_saved_independently(): void
    {
        $user = User::factory()->create(['newsletter_subscribed_at' => now()]);
        $other = User::factory()->create();
        $this->assertTrue($user->fresh()->project_phase_notifications);
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee(__('Projekteinstellungen & Benachrichtigungen'));
        $this->patch(route('profile.project-notifications.update'), [
            'project_phase_notifications' => '0',
            'id' => $other->id,
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->project_phase_notifications);
        $this->assertTrue($other->fresh()->project_phase_notifications);
        $this->assertNotNull($user->fresh()->newsletter_subscribed_at);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->patch('/profile', ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->project_phase_notifications);
        $this->patch(route('profile.project-notifications.update'), [
            'project_phase_notifications' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->project_phase_notifications);
        $this->patch(route('profile.project-notifications.update'), [
            'project_phase_notifications' => 'invalid',
        ])->assertSessionHasErrors('project_phase_notifications');
    }

    public function test_activity_is_recorded_and_throttled_for_authenticated_requests(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();
        $first = $user->fresh()->last_active_at;
        $this->assertTrue($first->equalTo(now()));
        $this->travel(30)->seconds();
        $this->get('/profile')->assertOk();
        $this->assertTrue($first->equalTo($user->fresh()->last_active_at));
        $this->travel(31)->seconds();
        $this->get('/profile')->assertOk();
        $this->assertTrue(now()->equalTo($user->fresh()->last_active_at));
    }

    public function test_guests_cannot_change_project_notification_settings(): void
    {
        $this->patch('/profile/project-notifications', [
            'project_phase_notifications' => '0',
        ])->assertRedirect('/login');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
