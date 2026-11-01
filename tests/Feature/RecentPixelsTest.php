<?php

namespace Tests\Feature;

use App\Models\Pixel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecentPixelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_pixels_newer_than_last_tile_run_are_returned(): void
    {
        $directory = sys_get_temp_dir().'/pixriot-tiles-'.uniqid();
        File::ensureDirectoryExists($directory.'/images/wall-tiles');
        config(['wall.output_path' => $directory]);
        file_put_contents($directory.'/images/wall-tiles/.last-run', now()->subMinutes(5)->format('Y-m-d H:i:s'));

        $old = Pixel::create(['x' => 1, 'y' => 1, 'color' => '#111111']);
        $old->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();
        Pixel::create(['x' => 2, 'y' => 2, 'color' => '#222222']);

        $this->getJson('/api/pixels/recent')
            ->assertOk()
            ->assertJsonCount(1, 'pixels')
            ->assertJsonPath('pixels.0.x', 2)
            ->assertJsonPath('pixels.0.color', '#222222');

        File::deleteDirectory($directory);
    }

    public function test_falls_back_to_fifteen_minutes_without_tile_state(): void
    {
        $directory = sys_get_temp_dir().'/pixriot-tiles-'.uniqid();
        File::ensureDirectoryExists($directory);
        config(['wall.output_path' => $directory]);

        $old = Pixel::create(['x' => 1, 'y' => 1, 'color' => '#111111']);
        $old->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();
        Pixel::create(['x' => 3, 'y' => 3, 'color' => '#333333']);

        $this->getJson('/api/pixels/recent')->assertOk()->assertJsonCount(1, 'pixels');

        File::deleteDirectory($directory);
    }

    public function test_since_parameter_limits_result_to_newer_changes(): void
    {
        $directory = sys_get_temp_dir().'/pixriot-tiles-'.uniqid();
        File::ensureDirectoryExists($directory);
        config(['wall.output_path' => $directory]);

        $first = Pixel::create(['x' => 1, 'y' => 1, 'color' => '#111111']);
        $first->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();
        Pixel::create(['x' => 2, 'y' => 2, 'color' => '#222222']);

        $response = $this->getJson('/api/pixels/recent')->assertOk()->assertJsonCount(2, 'pixels');
        $this->assertNotEmpty($response->json('server_time'));

        $this->getJson('/api/pixels/recent?since='.urlencode(now()->subMinute()->toIso8601String()))
            ->assertOk()
            ->assertJsonCount(1, 'pixels')
            ->assertJsonPath('pixels.0.x', 2);

        File::deleteDirectory($directory);
    }

    public function test_bounding_box_limits_result_to_requested_area(): void
    {
        $directory = sys_get_temp_dir().'/pixriot-tiles-'.uniqid();
        File::ensureDirectoryExists($directory);
        config(['wall.output_path' => $directory]);

        Pixel::create(['x' => 10, 'y' => 10, 'color' => '#111111']);
        Pixel::create(['x' => 900, 'y' => 900, 'color' => '#222222']);

        $this->getJson('/api/pixels/recent?x=0&y=0&width=300&height=300')
            ->assertOk()
            ->assertJsonCount(1, 'pixels')
            ->assertJsonPath('pixels.0.x', 10);

        File::deleteDirectory($directory);
    }
}
