<?php

namespace Tests\Feature;

use App\Models\Pixel;
use App\Services\Wall\WallExtender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WallExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function seedWall(int $width, int $height): void
    {
        DB::table('wall_dimensions')->where('id', 1)->update(['width' => $width, 'height' => $height]);
        $rows = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rows[] = ['x' => $x, 'y' => $y, 'color' => '#ff0000', 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Pixel::query()->insert($chunk);
        }
    }

    public function test_multiple_extensions_keep_the_wall_complete_and_consistent(): void
    {
        $this->seedWall(10, 5);
        $extender = app(WallExtender::class);

        foreach (['x', 'y', 'x', 'x', 'y', 'y', 'x'] as $axis) {
            $extender->extend($axis);
        }

        $wall = DB::table('wall_dimensions')->where('id', 1)->first();
        $this->assertSame([14, 8], [(int) $wall->width, (int) $wall->height]);
        $this->assertSame(14 * 8, Pixel::query()->count());
        $this->assertSame(14 * 8, DB::table('pixels')->select('x', 'y')->distinct()->count());
        $this->assertSame(0, Pixel::query()->where(fn ($q) => $q->where('x', '>=', 14)->orWhere('y', '>=', 8))->count());
        $this->assertSame(50, Pixel::query()->where('color', '#ff0000')->count());
    }

    public function test_scheduler_generates_tiles_for_extended_areas(): void
    {
        $output = storage_path('framework/testing/wall-output-extend');
        File::deleteDirectory($output);
        config(['wall.output_path' => $output]);

        try {
            $this->seedWall(300, 2);
            $this->artisan('wall:tiles')->assertSuccessful();
            $dir = $output.'/images/wall-tiles';
            $this->assertSame([300, 2], array_slice(getimagesize($dir.'/0-0.png'), 0, 2));
            $this->assertFileDoesNotExist($dir.'/300-0.png');

            $extender = app(WallExtender::class);
            $this->travel(10)->minutes();
            $extender->extend('x');
            $extender->extend('x');
            $extender->extend('y');
            $this->artisan('wall:tiles')->assertSuccessful();

            $this->assertSame([300, 3], array_slice(getimagesize($dir.'/0-0.png'), 0, 2));
            $this->assertSame([2, 3], array_slice(getimagesize($dir.'/300-0.png'), 0, 2));

            $this->travel(10)->minutes();
            $extender->extend('y');
            $this->artisan('wall:tiles')->assertSuccessful();
            $this->assertSame([300, 4], array_slice(getimagesize($dir.'/0-0.png'), 0, 2));
            $this->assertSame([2, 4], array_slice(getimagesize($dir.'/300-0.png'), 0, 2));

            $red = imagecreatefrompng($dir.'/0-0.png');
            $this->assertSame(0xFF0000, imagecolorat($red, 5, 1) & 0xFFFFFF);
            $white = imagecreatefrompng($dir.'/300-0.png');
            $this->assertSame(0xFFFFFF, imagecolorat($white, 1, 3) & 0xFFFFFF);
        } finally {
            File::deleteDirectory($output);
        }
    }
}
