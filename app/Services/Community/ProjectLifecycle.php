<?php

namespace App\Services\Community;

use App\Models\CommunityProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectLifecycle
{
    public function __construct(
        private WallReservationMutex $reservationMutex,
        private ProjectPhaseNotifier $phaseNotifier,
    ) {}

    public function refresh(CommunityProject $project): CommunityProject
    {
        [$updated, $changed] = DB::transaction(function () use ($project) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $previous = $current->status;
            if (! in_array($previous, CommunityProject::RUNNING_STATUSES, true)) {
                return [$current, false];
            }
            $leaderPresent = $current->group->members()->where('user_id', $current->created_by)->exists();
            $reason = null;
            if (! $leaderPresent) {
                $reason = 'leader_left';
            } elseif ($current->status === 'recruiting') {
                if (! $current->recruitment_close_at->isFuture()) {
                    $reason = 'recruitment_expired';
                } elseif ($current->hasMinimumParticipants()) {
                    if ($current->scheduled_start_at?->isFuture()) {
                        $current->update(['status' => 'scheduled']);
                    } else {
                        $this->start($current, now());
                    }
                }
            } elseif ($current->status === 'scheduled' && ! $current->scheduled_start_at->isFuture()) {
                if (! $current->hasMinimumParticipants()) {
                    $reason = 'insufficient_participants';
                } else {
                    $this->start($current, $current->scheduled_start_at);
                }
            }
            if ($current->status === 'active' && ! $current->project_close_at->isFuture()) {
                $reason = 'completion_expired';
            } elseif ($current->status === 'active' && $current->participantCount() === 0) {
                $reason = 'no_participants';
            }
            if ($reason) {
                $current->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_at' => now()]);
            }

            return [$current->fresh(), $previous !== $current->status];
        });
        if ($changed) {
            $this->phaseNotifier->notify($updated);
        }

        return $updated;
    }

    private function start(CommunityProject $project, Carbon $at): void
    {
        $project->update(['status' => 'active', 'started_at' => $at, 'project_close_at' => $at->copy()->addDays(7)]);
    }

    public function refreshForUser(int $userId): void
    {
        foreach (CommunityProject::whereIn('status', CommunityProject::RUNNING_STATUSES)
            ->where(fn ($query) => $query->where('created_by', $userId)
                ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', $userId)))
            ->orderBy('id')->get() as $project) {
            $this->refresh($project);
        }
    }

    public function cancelByStarter(CommunityProject $project, int $userId): CommunityProject
    {
        $updated = DB::transaction(function () use ($project, $userId) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->created_by === $userId, 403);
            abort_unless(in_array($current->status, CommunityProject::RUNNING_STATUSES, true), 409);
            $current->update(['status' => 'cancelled', 'cancellation_reason' => 'starter_cancelled', 'cancelled_at' => now()]);

            return $current->fresh();
        });
        $this->phaseNotifier->notify($updated);

        return $updated;
    }

    public function publish(CommunityProject $project): CommunityProject
    {
        $updated = DB::transaction(function () use ($project) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'active' && $current->project_close_at->isFuture(), 409);
            if ($current->reserved_x === null || $current->reserved_y === null) {
                throw ValidationException::withMessages(['project' => __('Für dieses Projekt ist keine Leinwandfläche reserviert.')]);
            }
            $this->reservationMutex->lockRegion($current->reserved_x, $current->reserved_y, $current->width, $current->height);
            DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->firstOrFail();
            $pixels = $current->pixels()->orderBy('id')->get();
            $painted = $pixels->keyBy(fn ($pixel) => $pixel->x.':'.$pixel->y);
            $now = now();
            $chunk = [];
            for ($y = 0; $y < $current->height; $y++) {
                for ($x = 0; $x < $current->width; $x++) {
                    $pixel = $painted->get($x.':'.$y);
                    $chunk[] = [
                        'x' => $current->reserved_x + $x, 'y' => $current->reserved_y + $y,
                        'color' => $pixel?->color ?? '#ffffff', 'owner_user_id' => $pixel?->user_id,
                        'updated_by_user_id' => $pixel?->user_id, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    if (count($chunk) === 500) {
                        DB::table('pixels')->upsert($chunk, ['x', 'y'], ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']);
                        $chunk = [];
                    }
                }
            }
            if ($chunk) {
                DB::table('pixels')->upsert($chunk, ['x', 'y'], ['color', 'owner_user_id', 'updated_by_user_id', 'updated_at']);
            }
            DB::table('pixel_edit_locks')
                ->whereBetween('x', [$current->reserved_x, $current->reserved_x + $current->width - 1])
                ->whereBetween('y', [$current->reserved_y, $current->reserved_y + $current->height - 1])->delete();
            $image = imagecreatetruecolor($current->width, $current->height);
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            foreach ($pixels as $pixel) {
                $color = imagecolorallocate($image, hexdec(substr($pixel->color, 1, 2)),
                    hexdec(substr($pixel->color, 3, 2)), hexdec(substr($pixel->color, 5, 2)));
                imagesetpixel($image, $pixel->x, $pixel->y, $color);
            }
            ob_start();
            $saved = imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            $path = 'community-projects/'.$current->id.'.png';
            if (! $saved || ! Storage::disk('public')->put($path, $bytes)) {
                throw new RuntimeException('Community project image could not be saved.');
            }
            $current->update(['status' => 'published', 'image_path' => $path,
                'published_at' => now(), 'reserved_until' => now()->addWeeks(7)]);

            return $current->fresh();
        });
        $this->phaseNotifier->notify($updated);

        return $updated;
    }
}
