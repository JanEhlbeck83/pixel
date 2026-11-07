<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallArtwork extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pixels' => 'array', 'is_public' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
