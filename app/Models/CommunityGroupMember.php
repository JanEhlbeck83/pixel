<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityGroupMember extends Model
{
    protected $fillable = ['community_group_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return ['last_active_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'community_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
