<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectParticipant extends Model
{
    protected $fillable = ['community_project_id', 'user_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
