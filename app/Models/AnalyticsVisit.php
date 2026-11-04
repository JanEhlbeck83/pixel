<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'visit_date',
        'visitor_hash',
        'country_code',
        'duration_seconds',
        'pixels_placed',
        'started_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'duration_seconds' => 'integer',
            'pixels_placed' => 'integer',
        ];
    }
}
