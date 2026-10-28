<?php

namespace App\Services\Wall;

use App\Models\Pixel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class WallExtender
{
    public const EXTENSION_SIZE = 1;

    private const MAX_COORDINATE = 2147483000;

    /** @return array{width:int,height:int} */
    public function extend(string $axis): array
    {
        return DB::transaction(function () use ($axis) {
            $wall = DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->first();
            $width = (int) $wall->width;
            $height = (int) $wall->height;

            if (($axis === 'x' && $width > self::MAX_COORDINATE) || ($axis === 'y' && $height > self::MAX_COORDINATE)) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => __('Die Pixelwand hat die maximale Koordinatengröße erreicht.'),
                ], 422));
            }

            $now = now();
            $rows = [];
            $flush = function () use (&$rows) {
                if ($rows !== []) {
                    Pixel::query()->insert($rows);
                    $rows = [];
                }
            };

            if ($axis === 'x') {
                for ($y = 0; $y < $height; $y++) {
                    for ($x = $width; $x < $width + self::EXTENSION_SIZE; $x++) {
                        $rows[] = $this->whiteRow($x, $y, $now);
                    }
                    if (count($rows) >= 2000) {
                        $flush();
                    }
                }
                $width += self::EXTENSION_SIZE;
            } else {
                for ($y = $height; $y < $height + self::EXTENSION_SIZE; $y++) {
                    for ($x = 0; $x < $width; $x++) {
                        $rows[] = $this->whiteRow($x, $y, $now);
                        if (count($rows) >= 2000) {
                            $flush();
                        }
                    }
                }
                $height += self::EXTENSION_SIZE;
            }
            $flush();

            DB::table('wall_dimensions')->where('id', 1)->update([
                'width' => $width,
                'height' => $height,
                'updated_at' => $now,
            ]);

            return ['width' => $width, 'height' => $height];
        });
    }

    private function whiteRow(int $x, int $y, $now): array
    {
        return [
            'x' => $x,
            'y' => $y,
            'color' => '#ffffff',
            'owner_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
