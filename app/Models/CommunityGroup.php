<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityGroup extends Model
{
    protected $fillable = [
        'founder_id', 'approved_by', 'name', 'description', 'emblem', 'status', 'admin_note', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function founder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founder_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommunityGroupMember::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CommunityProject::class);
    }
}
