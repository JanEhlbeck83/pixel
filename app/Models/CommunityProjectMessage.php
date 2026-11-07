<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectMessage extends Model
{
    protected $fillable = ['community_project_id', 'user_id', 'message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
