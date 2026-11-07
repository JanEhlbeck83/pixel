<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityProject extends Model
{
    public const RUNNING_STATUSES = ['recruiting', 'scheduled', 'active'];

    public const MIN_PARTICIPANTS = 3;

    public const MAX_RUNNING = 5;

    public const STATUS_LABELS = [
        'recruiting' => 'Anmeldung offen', 'scheduled' => 'Start geplant', 'active' => 'In Arbeit',
        'published' => 'Veröffentlicht', 'cancelled' => 'Verfallen oder abgebrochen', 'archived' => 'Archiviert',
    ];

    public const CANCELLATION_MESSAGES = [
        'recruitment_expired' => 'Innerhalb von 24 Stunden haben sich nicht genügend Mitglieder angemeldet.',
        'insufficient_participants' => 'Zum geplanten Start waren nicht genügend Mitglieder angemeldet.',
        'completion_expired' => 'Das Projekt wurde nicht innerhalb von sieben Tagen abgeschlossen.',
        'leader_left' => 'Die Projektleitung hat die Gruppe verlassen.',
        'no_participants' => 'Das Projekt hat keine Teilnehmenden mehr.',
        'starter_cancelled' => 'Das Projekt wurde von der Projektleitung abgebrochen.',
        'legacy_workflow' => 'Dieses Projekt stammt aus der früheren Projektplanung. Inhalte bleiben erhalten.',
    ];

    protected $fillable = [
        'community_group_id', 'created_by', 'title', 'type', 'width', 'height', 'status',
        'reserved_x', 'reserved_y', 'reserved_until', 'image_path', 'published_at',
        'template_image_path', 'crop_x', 'crop_y', 'recruitment_close_at',
        'project_close_at', 'proposals_close_at',
        'cancellation_reason', 'cancelled_at', 'minimum_participants',
        'expected_duration_hours', 'scheduled_start_at', 'started_at',
    ];

    protected function casts(): array
    {
        return [
            'recruitment_close_at' => 'datetime', 'project_close_at' => 'datetime',
            'scheduled_start_at' => 'datetime', 'started_at' => 'datetime',
            'reserved_until' => 'datetime', 'published_at' => 'datetime', 'cancelled_at' => 'datetime',
            'minimum_participants' => 'integer', 'expected_duration_hours' => 'integer',
        ];
    }

    public static function occupiedBy(int $userId): bool
    {
        return static::whereIn('status', self::RUNNING_STATUSES)
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', $userId)))
            ->exists();
    }

    public function isOpenForJoining(): bool
    {
        return in_array($this->status, self::RUNNING_STATUSES, true)
            && ($this->status !== 'recruiting' || $this->recruitment_close_at?->isFuture())
            && ($this->status !== 'active' || $this->project_close_at?->isFuture());
    }

    public function canBeReservedBy(User $user): bool
    {
        return $this->created_by === $user->id && $this->reserved_x === null
            && in_array($this->status, ['scheduled', 'active'], true);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'community_group_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CommunityProjectParticipant::class);
    }

    public function eligibleParticipants(): HasMany
    {
        return $this->participants()->whereHas('user.communityMembership',
            fn ($query) => $query->where('community_group_id', $this->community_group_id));
    }

    public function participantCount(): int
    {
        return $this->eligibleParticipants()->count();
    }

    public function hasMinimumParticipants(): bool
    {
        return $this->participantCount() >= $this->minimum_participants;
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(CommunityProjectPixel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunityProjectMessage::class);
    }
}
