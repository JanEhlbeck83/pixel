<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_feedback(): void
    {
        $response = $this->postJson('/feedback', [
            'email' => 'guest@example.com',
            'message' => 'Please add more colors.',
            'website' => '',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('feedback', [
            'user_id' => null,
            'email' => 'guest@example.com',
            'message' => 'Please add more colors.',
        ]);
    }

    public function test_feedback_from_honeypot_is_accepted_but_not_stored(): void
    {
        $response = $this->postJson('/feedback', [
            'message' => 'Automated spam submission.',
            'website' => 'spam.example',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_feedback_requires_a_valid_message_and_email(): void
    {
        $response = $this->postJson('/feedback', [
            'email' => 'not-an-email',
            'message' => 'no',
            'website' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'message']);
        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_feedback_submission_can_be_associated_with_signed_in_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/feedback', [
            'email' => null,
            'message' => 'The wall looks great.',
            'website' => '',
        ]);

        $response->assertCreated();
        $this->assertSame($user->id, Feedback::query()->value('user_id'));
    }

    public function test_feedback_submission_is_rate_limited(): void
    {
        foreach (range(1, 3) as $submission) {
            $this->postJson('/feedback', [
                'message' => "Feedback submission {$submission}.",
                'website' => '',
            ])->assertCreated();
        }

        $this->postJson('/feedback', [
            'message' => 'One submission too many.',
            'website' => '',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('feedback', 3);
    }
}
