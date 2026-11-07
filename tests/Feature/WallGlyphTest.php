<?php

namespace Tests\Feature;

use App\Models\CommunityGroup;
use App\Models\CommunityProject;
use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\User;
use App\Services\Wall\PixelGlyphs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WallGlyphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
    }

    private function stamp(array $overrides = []): array
    {
        return $overrides + [
            'font' => 'classic', 'size' => 7, 'letter' => 'A', 'x' => 20, 'y' => 20,
            'color' => '#123456', 'cost' => count(app(PixelGlyphs::class)->glyph('classic', 7, 'A')),
        ];
    }

    public function test_all_advertised_fonts_sizes_and_characters_use_fewer_than_fifty_pixels(): void
    {
        $catalog = app(PixelGlyphs::class)->catalog();
        $this->assertNotEmpty($catalog['sizes']);
        $this->assertContains(7, $catalog['sizes']);
        $maximum = 0;
        foreach ($catalog['fonts'] as $sizes) {
            $this->assertSame($catalog['sizes'], array_keys($sizes));
            foreach ($sizes as $glyphs) {
                $this->assertCount(36, $glyphs);
                foreach ($glyphs as $glyph) {
                    $this->assertGreaterThan(0, count($glyph));
                    $this->assertLessThan(50, count($glyph));
                    $this->assertCount(count($glyph), array_unique(array_map(fn ($p) => implode(':', $p), $glyph)));
                    $maximum = max($maximum, count($glyph));
                }
            }
        }
        fwrite(STDOUT, "\nGlyph catalog: sizes ".implode(',', $catalog['sizes'])."; maximum $maximum pixels; ".strlen(json_encode($catalog))." catalog bytes.\n");
    }

    public function test_wall_renders_palette_pipette_and_all_public_font_sizes(): void
    {
        $response = $this->get('/de')->assertOk();
        foreach (['wallPalettePipette', 'wallGlyphToggle', 'wallGlyphPanel', 'wallGlyphCatalog'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        foreach (app(PixelGlyphs::class)->catalog()['sizes'] as $size) {
            $response->assertSee('<option value="'.$size.'">'.$size.' px</option>', false);
        }
        $response->assertDontSee('<option value="12">12 px</option>', false);
    }

    public function test_stamping_is_one_bounded_transaction_and_charges_each_changed_pixel(): void
    {
        $user = User::factory()->create(['free_pixels' => 50]);
        $data = $this->stamp();
        $this->actingAs($user);
        DB::enableQueryLog();
        $start = hrtime(true);
        $response = $this->postJson('/api/pixels/glyph', $data)->assertOk()->assertJsonPath('count', $data['cost']);
        $elapsed = (hrtime(true) - $start) / 1e6;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertJsonPath('quota.free', 50 - $data['cost']);
        $this->assertSame($data['cost'], Pixel::where('color', '#123456')->count());
        $this->assertDatabaseMissing('pixels', ['x' => 20, 'y' => 20]);
        $this->assertLessThan(35, $queries);
        fwrite(STDOUT, "\nSQLite stamp: $queries SQL statements; ".round($elapsed, 2)." ms including HTTP test harness; {$data['cost']} changed pixels.\n");
    }

    public function test_largest_glyph_crossing_four_tiles_is_bounded_and_own_locks_are_allowed(): void
    {
        $catalog = app(PixelGlyphs::class)->catalog();
        $data = null;
        foreach ($catalog['fonts'] as $font => $sizes) {
            foreach ($sizes as $size => $letters) {
                foreach ($letters as $letter => $points) {
                    if ($data === null || count($points) > $data['cost']) {
                        $data = $this->stamp(['font' => $font, 'size' => $size, 'letter' => (string) $letter, 'cost' => count($points), 'x' => 298, 'y' => 298]);
                    }
                }
            }
        }
        $user = User::factory()->create();
        PixelEditLock::create(['x' => 300, 'y' => 300, 'locked_by_user_id' => $user->id, 'expires_at' => now()->addMinute()]);
        $this->actingAs($user);
        DB::enableQueryLog();
        $start = hrtime(true);
        $this->postJson('/api/pixels/glyph', $data)->assertOk()->assertJsonPath('count', $data['cost']);
        $elapsed = (hrtime(true) - $start) / 1e6;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $writes = array_filter($queries, fn ($query) => str_starts_with($query['query'], 'insert into "pixels"'));
        $this->assertCount(1, $writes);
        $this->assertLessThan(35, count($queries));
        fwrite(STDOUT, "\nLargest SQLite stamp: ".count($queries).' SQL statements; '.round($elapsed, 2)." ms including HTTP test harness; {$data['cost']} changed pixels, one upsert.\n");
    }

    public function test_insufficient_combined_quota_rejects_all_and_free_pixels_are_used_before_credits(): void
    {
        $data = $this->stamp();
        $user = User::factory()->create(['free_pixels' => 2, 'free_pixels_at' => now(), 'pixel_credits' => $data['cost'] - 3]);
        $this->actingAs($user)->postJson('/api/pixels/glyph', $data)->assertStatus(429);
        $this->assertSame(2, $user->fresh()->free_pixels);
        $this->assertSame(0, Pixel::count());
        $user->forceFill(['pixel_credits' => $data['cost'] - 2])->save();
        $this->postJson('/api/pixels/glyph', $data)->assertOk()->assertJsonPath('quota.free', 0)->assertJsonPath('quota.credits', 0);
    }

    public function test_only_changed_pixels_are_charged_and_stale_price_never_charges_extra(): void
    {
        $data = $this->stamp();
        $user = User::factory()->create();
        $point = app(PixelGlyphs::class)->glyph('classic', 7, 'A')[0];
        Pixel::create(['x' => 20 + $point[0], 'y' => 20 + $point[1], 'color' => '#123456']);
        $this->actingAs($user)->postJson('/api/pixels/glyph', $data)->assertStatus(409)->assertJsonPath('cost', $data['cost'] - 1);
        $this->assertSame(50, $user->fresh()->free_pixels);
        $this->postJson('/api/pixels/glyph', $this->stamp(['cost' => $data['cost'] - 1]))->assertOk()->assertJsonPath('count', $data['cost'] - 1);
        $free = $user->fresh()->free_pixels;
        $this->postJson('/api/pixels/glyph', $this->stamp(['cost' => 0]))->assertOk()->assertJsonPath('count', 0);
        $this->assertSame($free, $user->fresh()->free_pixels);
    }

    public function test_any_active_edit_lock_or_recent_other_user_pixel_rejects_entire_stamp(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $last = collect(app(PixelGlyphs::class)->glyph('classic', 7, 'A'))->last();
        $lock = PixelEditLock::create([
            'x' => 20 + $last[0], 'y' => 20 + $last[1], 'locked_by_user_id' => $other->id, 'expires_at' => now()->addMinute(),
        ]);
        $this->actingAs($user)->postJson('/api/pixels/glyph', $this->stamp())->assertStatus(423);
        $this->assertSame(0, Pixel::count());
        $this->assertSame(50, $user->fresh()->free_pixels);
        $lock->update(['expires_at' => now()]);
        $pixel = Pixel::create(['x' => 20 + $last[0], 'y' => 20 + $last[1], 'color' => '#ffffff', 'updated_by_user_id' => $other->id]);
        $this->postJson('/api/pixels/glyph', $this->stamp())->assertStatus(423);
        $this->assertSame(1, Pixel::count());
        $pixel->timestamps = false;
        $pixel->updated_at = now()->subMinutes(10);
        $pixel->save();
        $this->postJson('/api/pixels/glyph', $this->stamp())->assertOk();
    }

    public function test_reservations_check_actual_ink_pixels_not_transparent_background(): void
    {
        $user = User::factory()->create();
        $project = CommunityProject::create([
            'community_group_id' => CommunityGroup::firstOrFail()->id, 'created_by' => $user->id,
            'title' => 'Protected', 'type' => 'free', 'status' => 'active', 'width' => 1, 'height' => 1,
            'reserved_x' => 20, 'reserved_y' => 20, 'proposals_close_at' => now()->subDay(), 'project_close_at' => now()->addDay(),
        ]);
        $this->actingAs($user)->postJson('/api/pixels/glyph', $this->stamp())->assertOk();
        $project->update(['reserved_x' => 21]);
        $this->postJson('/api/pixels/glyph', $this->stamp(['cost' => 0]))->assertStatus(423);
        $project->update(['status' => 'published', 'reserved_until' => now()->addMinute()]);
        $this->postJson('/api/pixels/glyph', $this->stamp(['cost' => 0]))->assertStatus(423);
        $project->update(['reserved_until' => now()]);
        $this->postJson('/api/pixels/glyph', $this->stamp(['cost' => 0]))->assertOk();
    }

    public function test_invalid_or_outside_or_banned_requests_never_consume_quota(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['letter' => 'AB'], ['letter' => 'a'], ['size' => 14], ['font' => 'unknown'], ['x' => -1]] as $override) {
            $this->postJson('/api/pixels/glyph', $this->stamp($override))->assertUnprocessable();
        }
        $this->postJson('/api/pixels/glyph', $this->stamp(['x' => 1023]))->assertUnprocessable();
        $user->forceFill(['banned_at' => now()])->save();
        $this->postJson('/api/pixels/glyph', $this->stamp())->assertForbidden();
        $this->assertSame(50, $user->fresh()->free_pixels);
        $this->assertSame(0, Pixel::count());
    }

    public function test_guests_share_existing_guest_quota_and_cannot_partially_spend_it(): void
    {
        $data = $this->stamp();
        $this->postJson('/api/pixels/glyph', $data)->assertOk()->assertJsonPath('quota.free', 50 - $data['cost']);
        $this->getJson('/api/pixels/status')->assertJsonPath('free', 50 - $data['cost']);
        $this->postJson('/api/pixels/glyph', $this->stamp(['x' => 40]))->assertOk();
        $this->postJson('/api/pixels/glyph', $this->stamp(['x' => 60]))->assertStatus(429);
        $this->assertSame(2 * $data['cost'], Pixel::count());
    }

    public function test_database_failure_rolls_back_both_quota_and_pixels(): void
    {
        $user = User::factory()->create();
        DB::unprepared("CREATE TRIGGER reject_glyph BEFORE INSERT ON pixels BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->actingAs($user)->postJson('/api/pixels/glyph', $this->stamp())->assertStatus(500);
        $this->assertSame(50, $user->fresh()->free_pixels);
        $this->assertSame(0, Pixel::count());
    }
}
