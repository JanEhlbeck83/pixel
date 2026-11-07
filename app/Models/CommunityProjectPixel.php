<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectPixel extends Model
{
    protected $fillable = ['community_project_id', 'user_id', 'x', 'y', 'color'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }
}
