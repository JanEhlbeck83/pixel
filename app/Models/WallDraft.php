<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WallDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pixels' => 'array', 'paid_cells' => 'array', 'version' => 'integer', 'expires_at' => 'datetime'];
    }
}
