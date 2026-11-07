<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SoakTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pixriot-soak-'.Str::uuid();
        config(['soak.manifest' => $this->directory.DIRECTORY_SEPARATOR.'manifest.json']);
    }

    protected function tearDown(): void
    {
        if (is_file(config('soak.manifest'))) {
            unlink(config('soak.manifest'));
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    private function enable(): void
    {
        $this->app['env'] = 'staging';
        config([
            'soak.enabled' => true, 'mail.default' => 'array',
            'app.url' => 'https://staging.pixriot.online',
        ]);
    }

    public function test_preparation_fails_closed_without_staging_and_explicit_confirmation(): void
    {
        $this->artisan('soak:prepare')->assertFailed();
        $this->assertSame(0, User::count());
        $this->enable();
        $this->artisan('soak:prepare')->assertFailed();
        $this->assertSame(0, User::count());
        $this->app['env'] = 'production';
        $this->artisan('soak:prepare --confirm-isolated')->assertFailed();
        $this->assertFileDoesNotExist(config('soak.manifest'));
    }

    public function test_nonempty_database_wrong_target_and_outgoing_mail_are_rejected(): void
    {
        $this->enable();
        config(['mail.default' => 'smtp']);
        $this->artisan('soak:prepare --confirm-isolated')->assertFailed();
        config(['mail.default' => 'array', 'app.url' => 'https://pixriot.online']);
        $this->artisan('soak:prepare --confirm-isolated')->assertFailed();
        config(['app.url' => 'https://staging.pixriot.online']);
        $user = User::factory()->create();
        $this->artisan('soak:prepare --confirm-isolated')->assertFailed();
        $this->assertSame([$user->id], User::pluck('id')->all());
        $this->assertFileDoesNotExist(config('soak.manifest'));
    }

    public function test_fixtures_use_real_quota_permissions_chat_and_never_create_orders(): void
    {
        $this->enable();
        $this->artisan('soak:prepare --confirm-isolated')->assertSuccessful();
        $manifest = json_decode(file_get_contents(config('soak.manifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(50, $manifest['users']);
        $this->assertSame(50, User::count());
        $this->assertSame(3840, DB::table('wall_dimensions')->value('width'));
        $this->assertSame(2160, DB::table('wall_dimensions')->value('height'));
        $this->assertSame(2, CommunityProject::count());
        $this->assertSame(50, DB::table('community_group_members')->count());
        $this->assertSame(50, DB::table('community_project_participants')->count());
        $this->assertSame(0, DB::table('orders')->count());
        $user = User::findOrFail($manifest['users'][0]['id']);
        $this->assertTrue(password_verify($manifest['users'][0]['password'], $user->password));
        $this->assertFalse($user->project_phase_notifications);
        $this->assertSame(0, $user->pixel_credits);
        $this->assertSame(50, $user->free_pixels);
        $status = $this->actingAs($user)->getJson('/api/soak/status')->assertOk()
            ->assertJsonPath('run_id', $manifest['run_id'])->assertJsonPath('users', 50);
        $this->assertStringNotContainsString($manifest['users'][0]['password'], $status->getContent());
        $this->withSession(['_token' => 'soak-csrf'])->withHeader('X-CSRF-TOKEN', 'soak-csrf');
        $this->postJson('/api/pixels/lock', ['x' => 64, 'y' => 180, 'width' => 1, 'height' => 1])->assertOk();
        $this->postJson('/api/pixels', ['x' => 64, 'y' => 180, 'color' => '#123456'])->assertOk();
        $project = CommunityProject::firstOrFail();
        $this->postJson('/de/community/projects/'.$project->id.'/pixels', [
            'x' => 0, 'y' => 0, 'color' => '#ff0000',
        ])->assertOk()->assertJsonPath('quota.free', 48);
        $this->postJson('/de/community/projects/'.$project->id.'/chat', ['message' => 'Synthetic test'])
            ->assertCreated()->assertJsonPath('message.message', 'Synthetic test');
        $this->getJson('/de/community/projects/'.$project->id.'/chat')->assertOk()
            ->assertJsonCount(1, 'messages');
        $this->travel(15)->days();
        $this->getJson('/de/community/projects/'.$project->id.'/pixels')->assertOk();
        $this->assertSame('active', $project->fresh()->status);
        $hash = hash_file('sha256', config('soak.manifest'));
        $this->artisan('soak:prepare --confirm-isolated')->assertFailed();
        $this->assertSame($hash, hash_file('sha256', config('soak.manifest')));
        $this->assertSame(50, User::count());
    }

    public function test_status_is_hidden_disabled_or_production_and_detects_expiry_and_other_users(): void
    {
        $this->enable();
        $this->artisan('soak:prepare --confirm-isolated')->assertSuccessful();
        $user = User::firstOrFail();
        $this->actingAs($user);
        config(['soak.enabled' => false]);
        $this->getJson('/api/soak/status')->assertNotFound();
        config(['soak.enabled' => true]);
        $this->app['env'] = 'production';
        $this->getJson('/api/soak/status')->assertNotFound();
        $this->app['env'] = 'staging';
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson('/api/soak/status')->assertForbidden();
        $this->actingAs($user)->getJson('/api/soak/status')->assertConflict();
        $this->travel(17)->days();
        $this->getJson('/api/soak/status')->assertGone();
    }
}
