<?php

namespace Tests\Feature;

use App\Models\Pixel;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixelWallPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_the_pixel_wall(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wall');

        $response->assertOk();
        $response->assertSee('PIXEL CHAOS');
    }

    public function test_pixelchaos_wall_reset_requires_confirmation_before_deleting_pixels(): void
    {
        Pixel::query()->create([
            'x' => 10,
            'y' => 20,
            'color' => '#123456',
            'owner_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        $this->artisan('wall:reset-pixelchaos')
            ->expectsConfirmation(
                'This will delete all current wall pixels and locks, reset its dimensions, and generate new tiles. Continue?',
                'no'
            )
            ->assertExitCode(1);

        $this->assertDatabaseHas('pixels', ['x' => 10, 'y' => 20, 'color' => '#123456']);
        $this->assertDatabaseHas('wall_dimensions', ['id' => 1, 'width' => 1024, 'height' => 1024]);
    }

    public function test_wall_page_contains_public_domain_reference_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wall');

        $response->assertOk();
        $response->assertSee('Official_Presidential_Portrait');
        $response->assertSee('Public domain');
    }

    public function test_wall_page_uses_canvas_popup_editor_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wall');

        $response->assertOk();
        $response->assertSee('editorPopup');
        $response->assertSee('class="wall-page"', false);
        $response->assertDontSee('images/current-wall.png');
        $response->assertSee('id="wallBackground"', false);
        $response->assertSee('id="zoomResetBtn"', false);
        $response->assertSee('100%', false);
        $response->assertSee('id="wallMenuToggle"', false);
        $response->assertSee('id="wallMenuPanel"', false);
        $response->assertSee('id="feedbackForm"', false);
        $response->assertSee('id="reservationLoadingOverlay"', false);
        $response->assertSee('class="reservation-loading-spinner"', false);
        $response->assertSee('Viewport wird geladen …');
        $response->assertDontSee('reservation-loading-overlay hidden', false);
        $response->assertSee('class="status-live-region"', false);
        $response->assertSee('aria-label="300 Pixel nach oben"', false);
        $response->assertSee('aria-label="300 Pixel nach rechts"', false);
        $response->assertSee('aria-label="300 Pixel nach unten"', false);
        $response->assertSee('aria-label="300 Pixel nach links"', false);
        $response->assertSee('id="extendWallRightBtn"', false);
        $response->assertSee('id="extendWallDownBtn"', false);
        $response->assertDontSee('id="extendRightBtn"', false);
        $response->assertDontSee('id="extendDownBtn"', false);
        $response->assertDontSee('id="colorPalette"');
        $this->assertDatabaseCount('pixels', 0);
    }

    public function test_snapshot_generation_creates_an_image_and_records_it(): void
    {
        $staleTilePath = public_path('images/wall-tiles/3900-2100.png');
        $previousStaleTile = file_exists($staleTilePath) ? file_get_contents($staleTilePath) : null;
        file_put_contents($staleTilePath, 'stale');

        try {
            Pixel::query()->create([
                'x' => 0,
                'y' => 0,
                'color' => '#ff0000',
                'owner_user_id' => null,
                'updated_by_user_id' => null,
            ]);

            $this->artisan('snapshot:generate')->assertSuccessful();

            $this->assertDatabaseCount('snapshots', 1);

            $imagePath = Snapshot::latest()->value('image_path');
            $this->assertNotNull($imagePath);
            $this->assertStringStartsWith('images/snapshots/', $imagePath);
            $this->assertFileExists(public_path($imagePath));
            $this->assertFileExists(public_path('images/wall-tiles/0-0.png'));
            $tileDimensions = getimagesize(public_path('images/wall-tiles/0-0.png'));
            $this->assertSame([300, 300], [$tileDimensions[0], $tileDimensions[1]]);
            $this->assertFileDoesNotExist($staleTilePath);
        } finally {
            if ($previousStaleTile === null) {
                if (file_exists($staleTilePath)) {
                    unlink($staleTilePath);
                }
            } else {
                file_put_contents($staleTilePath, $previousStaleTile);
            }
        }
    }

    public function test_selection_api_returns_only_pixels_from_the_selected_bounds(): void
    {
        Pixel::query()->insert([
            ['x' => 10, 'y' => 10, 'color' => '#111111', 'owner_user_id' => null, 'updated_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['x' => 20, 'y' => 20, 'color' => '#222222', 'owner_user_id' => null, 'updated_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['x' => 10, 'y' => 11, 'color' => '#333333', 'owner_user_id' => null, 'updated_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/api/pixels?x=10&y=10&width=5&height=5');

        $response->assertOk();
        $response->assertJsonPath('pixels.0.x', 10);
        $response->assertJsonPath('pixels.0.y', 10);
        $response->assertJsonCount(2, 'pixels');
    }

    public function test_wall_exposes_dimensions_and_generates_300_pixel_tiles(): void
    {
        $this->getJson('/api/wall')
            ->assertOk()
            ->assertJsonPath('width', 1024)
            ->assertJsonPath('height', 1024)
            ->assertJsonPath('tile_size', 300);

        $response = $this->get('/api/wall/tile?x=900&y=900');
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $image = imagecreatefromstring($response->getContent());
        $this->assertSame(124, imagesx($image));
        $this->assertSame(124, imagesy($image));
        imagedestroy($image);
    }

    public function test_authenticated_user_can_extend_wall_by_one_300_pixel_tile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/wall/extend', ['axis' => 'x']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('width', 1324)
            ->assertJsonPath('height', 1024);
        $this->assertDatabaseCount('pixels', 307200);
        $this->assertDatabaseHas('pixels', ['x' => 1024, 'y' => 0, 'color' => '#ffffff']);
        $this->assertDatabaseHas('pixels', ['x' => 1323, 'y' => 1023, 'color' => '#ffffff']);

        $tileResponse = $this->get('/api/wall/tile?x=900&y=0');
        $tileResponse->assertOk();
        $tile = imagecreatefromstring($tileResponse->getContent());
        $this->assertSame(300, imagesx($tile));
        $this->assertSame(300, imagesy($tile));
        $this->assertSame(0xFFFFFF, imagecolorat($tile, 124, 0) & 0xFFFFFF);
        imagedestroy($tile);
    }

    public function test_authenticated_user_can_extend_wall_down_by_one_300_pixel_tile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/wall/extend', ['axis' => 'y']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('width', 1024)
            ->assertJsonPath('height', 1324);
        $this->assertDatabaseCount('pixels', 307200);
        $this->assertDatabaseHas('pixels', ['x' => 0, 'y' => 1024, 'color' => '#ffffff']);
        $this->assertDatabaseHas('pixels', ['x' => 1023, 'y' => 1323, 'color' => '#ffffff']);
    }

    public function test_wall_extension_requires_authentication(): void
    {
        $this->postJson('/api/wall/extend', ['axis' => 'y'])->assertUnauthorized();
        $this->assertDatabaseCount('pixels', 0);
    }

    public function test_authenticated_user_can_update_multiple_pixels_in_one_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/pixels/lock', [
            'x' => 10,
            'y' => 10,
            'width' => 5,
            'height' => 5,
        ])->assertOk();

        $response = $this->actingAs($user)->post('/api/pixels', [
            'pixels' => [
                ['x' => 10, 'y' => 10, 'color' => '#111111'],
                ['x' => 11, 'y' => 10, 'color' => '#222222'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('pixels', ['x' => 10, 'y' => 10, 'color' => '#111111']);
        $this->assertDatabaseHas('pixels', ['x' => 11, 'y' => 10, 'color' => '#222222']);
    }

    public function test_user_locks_every_pixel_in_the_selected_area_without_creating_pixel_rows(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/pixels/lock', [
            'x' => 10,
            'y' => 20,
            'width' => 5,
            'height' => 5,
        ]);

        $response->assertOk()->assertJsonPath('count', 25);
        $this->assertDatabaseCount('pixel_edit_locks', 25);
        $this->assertDatabaseCount('pixels', 0);
    }

    public function test_overlapping_selection_lock_is_rejected_without_partial_reservations(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->postJson('/api/pixels/lock', [
            'x' => 10,
            'y' => 10,
            'width' => 5,
            'height' => 5,
        ])->assertOk();

        $this->actingAs($secondUser)->postJson('/api/pixels/lock', [
            'x' => 14,
            'y' => 14,
            'width' => 5,
            'height' => 5,
        ])->assertStatus(423);

        $this->assertDatabaseCount('pixel_edit_locks', 25);
        $this->assertDatabaseHas('pixel_edit_locks', [
            'x' => 14,
            'y' => 14,
            'locked_by_user_id' => $firstUser->id,
        ]);
    }

    public function test_user_can_release_all_locks_for_their_selection(): void
    {
        $user = User::factory()->create();
        $area = ['x' => 10, 'y' => 20, 'width' => 5, 'height' => 5];

        $this->actingAs($user)->postJson('/api/pixels/lock', $area)->assertOk();
        $this->actingAs($user)->deleteJson('/api/pixels/lock', $area)->assertOk();

        $this->assertDatabaseCount('pixel_edit_locks', 0);
    }

    public function test_pixel_batch_write_requires_the_active_selection_reservation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/pixels', [
            'pixels' => [
                ['x' => 10, 'y' => 10, 'color' => '#111111'],
            ],
        ])->assertStatus(423);

        $this->assertDatabaseCount('pixels', 0);
    }

    public function test_pixel_cannot_be_edited_again_during_its_ten_minute_lock(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Pixel::query()->create([
            'x' => 30,
            'y' => 40,
            'color' => '#111111',
            'owner_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        Pixel::query()->where('x', 30)->where('y', 40)->update(['updated_at' => now()->subMinutes(5)]);

        $response = $this->actingAs($otherUser)->postJson('/api/pixels', [
            'x' => 30,
            'y' => 40,
            'color' => '#ffffff',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('locked_pixels.0.x', 30)
            ->assertJsonPath('locked_pixels.0.y', 40);
        $this->assertDatabaseHas('pixels', ['x' => 30, 'y' => 40, 'color' => '#111111']);
    }

    public function test_lock_api_reports_pixels_that_are_still_locked(): void
    {
        $owner = User::factory()->create();
        Pixel::query()->create([
            'x' => 30,
            'y' => 40,
            'color' => '#111111',
            'owner_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        Pixel::query()->where('x', 30)->where('y', 40)->update(['updated_at' => now()->subMinutes(5)]);

        $this->getJson('/api/pixels/locks')
            ->assertOk()
            ->assertJsonCount(1, 'locked_pixels')
            ->assertJsonPath('locked_pixels.0.x', 30)
            ->assertJsonPath('locked_pixels.0.y', 40);
    }

    public function test_background_version_api_reports_the_active_wall_image_version(): void
    {
        $this->getJson('/api/background-image/version')
            ->assertOk()
            ->assertJsonStructure(['version']);
    }

    public function test_pixel_can_be_edited_after_its_ten_minute_lock_expires(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Pixel::query()->create([
            'x' => 30,
            'y' => 40,
            'color' => '#111111',
            'owner_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        Pixel::query()->where('x', 30)->where('y', 40)->update(['updated_at' => now()->subMinutes(11)]);

        $this->actingAs($otherUser)->postJson('/api/pixels/lock', [
            'x' => 30,
            'y' => 40,
            'width' => 5,
            'height' => 5,
        ])->assertOk();

        $response = $this->actingAs($otherUser)->postJson('/api/pixels', [
            'x' => 30,
            'y' => 40,
            'color' => '#ffffff',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('pixels', ['x' => 30, 'y' => 40, 'color' => '#ffffff']);
    }

    public function test_locked_pixel_rejects_the_entire_selection_batch(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Pixel::query()->create([
            'x' => 30,
            'y' => 40,
            'color' => '#111111',
            'owner_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        Pixel::query()->where('x', 30)->where('y', 40)->update(['updated_at' => now()->subMinutes(5)]);

        $response = $this->actingAs($otherUser)->postJson('/api/pixels', [
            'pixels' => [
                ['x' => 31, 'y' => 40, 'color' => '#ffffff'],
                ['x' => 30, 'y' => 40, 'color' => '#ffffff'],
            ],
        ]);

        $response->assertStatus(423);
        $this->assertDatabaseMissing('pixels', ['x' => 31, 'y' => 40]);
        $this->assertDatabaseHas('pixels', ['x' => 30, 'y' => 40, 'color' => '#111111']);
    }
}
