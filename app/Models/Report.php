<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const REASONS = ['hate', 'insult', 'violence', 'sexual', 'copyright', 'other'];

    public const REASON_LABELS = [
        'hate' => 'Hassrede / verbotene Symbole',
        'insult' => 'Beleidigung',
        'violence' => 'Gewalt',
        'sexual' => 'Sexuelle Inhalte',
        'copyright' => 'Urheberrecht',
        'other' => 'Sonstiges',
    ];

    public const MAX_SIZE = 300;

    protected $fillable = [
        'reporter_user_id',
        'reporter_email',
        'reason',
        'message',
        'x',
        'y',
        'width',
        'height',
        'status',
        'action',
        'handled_by_user_id',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function snapshotPath(): string
    {
        return storage_path('app/private/reports/'.$this->id.'.png');
    }
}
