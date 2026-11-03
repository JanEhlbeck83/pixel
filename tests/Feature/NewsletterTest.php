<?php

namespace Tests\Feature;

use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['wall.admin_emails' => ['admin@example.com']]);

        return User::factory()->create(['email' => 'admin@example.com']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Neues auf PixRiot',
            'preheader' => 'Kurz und knapp',
            'from_name' => 'PixRiot',
            'from_email' => 'news@pixriot.online',
            'template' => 'neon',
            'locale' => 'de',
            'headline' => 'Hallo Welt',
            'body' => "Erster **Absatz**.\n\nZweiter Absatz <script>alert(1)</script>.",
        ], $overrides);
    }

    public function test_only_admins_can_reach_the_newsletter_area(): void
    {
        $this->get('/admin/newsletters')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/newsletters')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/admin/newsletters', $this->payload())->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/newsletters')->assertOk()->assertSee('Neuer Newsletter');
        $this->assertDatabaseCount('newsletters', 0);
    }

    public function test_admin_creates_a_draft_with_images_and_sees_a_preview(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/newsletters', $this->payload([
            'header_image' => UploadedFile::fake()->image('kopf.png', 1200, 400),
            'body_image' => UploadedFile::fake()->image('unten.jpg', 800, 600),
            'button_label' => 'Zur Wand',
            'button_url' => 'https://pixriot.online',
            'template' => 'poster',
        ]));

        $newsletter = Newsletter::firstOrFail();
        $response->assertRedirect(route('admin.newsletters.edit', $newsletter));
        $this->assertSame('draft', $newsletter->status);
        Storage::disk('local')->assertExists($newsletter->header_image);
        Storage::disk('local')->assertExists($newsletter->body_image);

        $html = $this->actingAs($admin)->get(route('admin.newsletters.preview', $newsletter))->assertOk()->getContent();
        $this->assertStringContainsString('<strong>Absatz</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Zur Wand', $html);
        $this->assertStringContainsString(basename($newsletter->header_image), $html);
        $this->assertStringContainsString('#ff2d95', $html);
    }

    public function test_all_templates_render(): void
    {
        $admin = $this->admin();

        foreach (array_keys(Newsletter::TEMPLATES) as $template) {
            $newsletter = Newsletter::create($this->payload(['template' => $template]));
            $this->actingAs($admin)->get(route('admin.newsletters.preview', $newsletter))
                ->assertOk()->assertSee('Hallo Welt');
        }
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin())->post('/admin/newsletters', $this->payload([
            'from_email' => 'kein-mail',
            'button_label' => 'Klick',
            'button_url' => 'javascript:alert(1)',
            'header_image' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors(['from_email', 'button_url', 'header_image']);

        $this->assertDatabaseCount('newsletters', 0);
    }

    public function test_test_mail_goes_only_to_the_requested_address(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $newsletter = Newsletter::create($this->payload());

        $this->actingAs($admin)->post(route('admin.newsletters.test', $newsletter))->assertRedirect();
        Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo('admin@example.com'));

        $this->actingAs($admin)->post(route('admin.newsletters.test', $newsletter), ['test_email' => 'x@example.com']);
        Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo('x@example.com'));
        Mail::assertSentCount(2);
    }

    public function test_sending_reaches_only_consenting_verified_unbanned_users_and_only_once(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $yes = User::factory()->create(['newsletter_subscribed_at' => now()]);
        User::factory()->create();
        User::factory()->unverified()->create(['newsletter_subscribed_at' => now()]);
        User::factory()->create(['newsletter_subscribed_at' => now(), 'banned_at' => now()]);
        $newsletter = Newsletter::create($this->payload());

        $this->actingAs($admin)->post(route('admin.newsletters.send', $newsletter))->assertSessionHasErrors('confirm');
        Mail::assertNothingSent();

        $this->actingAs($admin)->post(route('admin.newsletters.send', $newsletter), ['confirm' => 1])
            ->assertRedirect(route('admin.newsletters'));

        Mail::assertSentCount(1);
        Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo($yes->email));
        $this->assertSame('sent', $newsletter->fresh()->status);
        $this->assertSame(1, $newsletter->fresh()->recipient_count);

        $this->actingAs($admin)->post(route('admin.newsletters.send', $newsletter), ['confirm' => 1])->assertStatus(409);
        $this->actingAs($admin)->put(route('admin.newsletters.update', $newsletter), $this->payload())->assertForbidden();
        Mail::assertSentCount(1);
    }

    public function test_mail_contains_signed_unsubscribe_link_and_it_works(): void
    {
        $user = User::factory()->create(['newsletter_subscribed_at' => now()]);
        $newsletter = Newsletter::create($this->payload());

        $mail = new NewsletterMail($newsletter, $user);
        $url = $mail->unsubscribeUrl();
        $this->assertStringContainsString('signature=', $url);
        $mail->assertSeeInHtml(e($url), false);

        $this->get('/newsletter/unsubscribe/'.$user->id)->assertForbidden();
        $this->post($url)->assertOk();
        $this->assertNull($user->fresh()->newsletter_subscribed_at);
    }

    public function test_newsletter_images_are_public_but_restricted_to_the_newsletter_folder(): void
    {
        Storage::fake('local');
        $name = str_repeat('a', 40).'.png';
        Storage::disk('local')->put('newsletters/'.$name, 'x');

        $this->get(route('newsletter.image', $name))->assertOk();
        $this->get(route('newsletter.image', 'missing'.str_repeat('b', 20).'.png'))->assertNotFound();
        $this->get('/newsletter-images/..%2F..%2F.env')->assertNotFound();
    }

    public function test_users_can_opt_in_at_registration_and_in_the_profile(): void
    {
        $this->post('/de/register', [
            'name' => 'Neu', 'email' => 'neu@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'newsletter' => '1',
        ]);
        $this->assertNotNull(User::where('email', 'neu@example.com')->first()->newsletter_subscribed_at);

        $other = User::factory()->create();
        $this->assertNull($other->newsletter_subscribed_at);
        $this->actingAs($other)->patch('/profile', ['name' => $other->name, 'email' => $other->email, 'newsletter' => '1']);
        $this->assertNotNull($other->fresh()->newsletter_subscribed_at);
        $this->actingAs($other)->patch('/profile', ['name' => $other->name, 'email' => $other->email, 'newsletter' => '0']);
        $this->assertNull($other->fresh()->newsletter_subscribed_at);
    }
}
