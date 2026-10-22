<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'summary',
        'image_path',
        'created_at',
    ];

    protected $casts = [
        'summary' => 'array',
    ];
}
