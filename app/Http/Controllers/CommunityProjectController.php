<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\CommunityProjectParticipant;
use App\Models\User;
use App\Services\Community\ProjectLifecycle;
use App\Services\Community\ProjectTemplate;
use App\Services\Community\WallReservationMutex;
use App\Services\Wall\PixelQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunityProjectController extends Controller
{
    public function __construct(
        private ProjectLifecycle $lifecycle,
        private WallReservationMutex $reservationMutex,
        private PixelQuota $quota,
    ) {}

    public function show(Request $request, CommunityProject $project)
    {
        $project = $this->lifecycle->refresh($project)->load(['group', 'participants.user']);
        $isMember = $request->user()?->communityMembership()->where('community_group_id', $project->community_group_id)->exists() ?? false;
        $isProjectLeader = $request->user()?->id === $project->created_by;
        $participant = $request->user() && $project->participants()->where('user_id', $request->user()->id)->exists();
        $canJoinProject = $isMember && ! $participant && $project->isOpenForJoining()
            && ! CommunityProject::occupiedBy($request->user()->id);
        $canReserveProject = $isMember && $project->canBeReservedBy($request->user());

        return view('community.projects.show', compact('project', 'isMember', 'isProjectLeader', 'participant', 'canJoinProject', 'canReserveProject'));
    }

    public function join(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        $this->lifecycle->refreshForUser($request->user()->id);
        $project = $this->lifecycle->refresh($project);
        DB::transaction(function () use ($request, $project) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->ensureGroupMember($request->user(), $current);
            abort_unless($current->isOpenForJoining(), 409);
            if ($current->participants()->where('user_id', $request->user()->id)->exists()) {
                return;
            }
            if (CommunityProject::occupiedBy($request->user()->id)) {
                throw ValidationException::withMessages(['project' => __('Du leitest bereits ein Projekt oder bist für eines angemeldet.')]);
            }
            CommunityProjectParticipant::create(['community_project_id' => $current->id, 'user_id' => $request->user()->id]);
            $this->lifecycle->refresh($current);
        });

        return redirect()->route('community.groups.show', $project->community_group_id)->with('status', __('Du bist für das Projekt angemeldet.'));
    }

    public function cancel(Request $request, CommunityProject $project)
    {
        $request->validate(['confirm_cancel' => ['accepted']]);
        $this->lifecycle->cancelByStarter($this->lifecycle->refresh($project), $request->user()->id);

        return back()->with('status', __('Das Projekt wurde von der Projektleitung abgebrochen. Entwürfe und Projektpixel bleiben erhalten.'));
    }

    public function complete(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        abort_unless($request->user()->id === $project->created_by, 403);
        $request->validate(['confirm_publish' => ['accepted']]);
        $this->lifecycle->publish($this->lifecycle->refresh($project));

        return back()->with('status', __('Das Projekt wurde abgeschlossen und veröffentlicht.'));
    }

    public function reserve(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        $project = $this->lifecycle->refresh($project);
        abort_unless($project->canBeReservedBy($request->user()), 403);
        $data = $request->validate(['x' => ['required', 'integer', 'min:0'], 'y' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $project, $data) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->ensureGroupMember($request->user(), $current);
            abort_unless($current->canBeReservedBy($request->user())
                && ($current->status !== 'active' || $current->project_close_at->isFuture()), 409);
            $this->reservationMutex->lockRegion($data['x'], $data['y'], $current->width, $current->height);
            $dimensions = DB::table('wall_dimensions')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($dimensions->width < $data['x'] + $current->width || $dimensions->height < $data['y'] + $current->height) {
                throw ValidationException::withMessages(['x' => __('Die ausgewählte Fläche liegt außerhalb der aktuellen Leinwand.')]);
            }
            if ($this->reservationMutex->reservationAt($data['x'], $data['y'], $current->width, $current->height)
                || $this->reservationMutex->activePixelLocks($data['x'], $data['y'], $current->width, $current->height)->isNotEmpty()) {
                throw ValidationException::withMessages(['x' => __('Diese Fläche ist bereits reserviert oder enthält gesperrte Pixel.')]);
            }
            $current->update(['reserved_x' => $data['x'], 'reserved_y' => $data['y']]);
        });

        return redirect()->route('community.projects.show', $project)->with('status', __('Die Projektfläche wurde reserviert.'));
    }

    public function templateImage(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        abort_unless($project->template_image_path, 404);

        return app(ProjectTemplate::class)->image($project->template_image_path, $project->width, $project->height,
            $project->crop_x ?? 0, $project->crop_y ?? 0, $request->boolean('original'));
    }

    public function image(CommunityProject $project)
    {
        abort_unless($project->status === 'published' && $project->image_path
            && Storage::disk('public')->exists($project->image_path), 404);

        return response()->file(Storage::disk('public')->path($project->image_path));
    }

    public function updateTemplate(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        abort_unless($request->user()->id === $project->created_by, 403);
        $data = $request->validate(['crop_x' => ['required', 'integer', 'min:0'], 'crop_y' => ['required', 'integer', 'min:0']]);
        $project = $this->lifecycle->refresh($project);
        DB::transaction(function () use ($project, $data) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($current->status, CommunityProject::RUNNING_STATUSES, true)
                && ($current->status !== 'active' || $current->project_close_at->isFuture()), 409);
            abort_unless($current->template_image_path && Storage::disk('local')->exists($current->template_image_path), 404);
            app(ProjectTemplate::class)->validateCrop(Storage::disk('local')->path($current->template_image_path),
                $current->width, $current->height, $data['crop_x'], $data['crop_y']);
            $current->update($data);
        });

        return back()->with('status', __('Die gemeinsame Vorlage wurde aktualisiert. Bereits gemalte Pixel bleiben unverändert.'));
    }

    public function pixels(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        $project = $this->lifecycle->refresh($project);
        $this->ensureParticipant($request->user(), $project);

        return response()->json(['width' => $project->width, 'height' => $project->height,
            'pixels' => $project->pixels()->get(['x', 'y', 'color'])]);
    }

    public function paint(Request $request, CommunityProject $project)
    {
        $this->ensureGroupMember($request->user(), $project);
        $project = $this->lifecycle->refresh($project);
        $this->ensureParticipant($request->user(), $project);
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0', 'lt:'.max(1, $project->width)],
            'y' => ['required', 'integer', 'min:0', 'lt:'.max(1, $project->height)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        return DB::transaction(function () use ($request, $project, $data) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->ensureGroupMember($request->user(), $current);
            $this->ensureParticipant($request->user(), $current);
            abort_unless($current->status === 'active' && $current->project_close_at->isFuture()
                && $current->reserved_x !== null, 409, __('Das Projekt ist noch nicht bereit oder bereits beendet.'));
            $denied = $this->quota->consume($request->user());
            if ($denied !== null) {
                return response()->json($denied + ['success' => false, 'message' => __('Dein Pixelvorrat ist leer.')], 429);
            }
            $now = now();
            DB::table('community_project_pixels')->upsert([[
                'community_project_id' => $current->id, 'user_id' => $request->user()->id,
                'x' => $data['x'], 'y' => $data['y'], 'color' => $data['color'],
                'created_at' => $now, 'updated_at' => $now,
            ]], ['community_project_id', 'x', 'y'], ['user_id', 'color', 'updated_at']);
            $request->user()->refresh();

            return response()->json(['success' => true, 'quota' => $this->quota->status($request)]);
        });
    }

    public function chat(Request $request, CommunityProject $project)
    {
        $project = $this->lifecycle->refresh($project);
        $this->authorizeChat($request, $project);
        $query = $project->messages()->with('user:id,name');
        $after = $request->integer('after_id');
        $before = $request->integer('before_id');
        if ($after > 0) {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(100)->get();
            $hasOlder = false;
        } else {
            if ($before > 0) {
                $query->where('id', '<', $before);
            }
            $messages = $query->orderByDesc('id')->limit(101)->get();
            $hasOlder = $messages->count() > 100;
            $messages = $messages->take(100)->reverse()->values();
        }

        return response()->json(['messages' => $messages->map(fn ($message) => [
            'id' => $message->id, 'user' => $message->user?->name ?? __('Gelöschtes Konto'),
            'message' => $message->message, 'created_at' => $message->created_at->toIso8601String(),
        ]), 'has_older' => $hasOlder]);
    }

    public function sendChatMessage(Request $request, CommunityProject $project)
    {
        $project = $this->lifecycle->refresh($project);
        $this->authorizeChat($request, $project);
        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:1000']]);

        return DB::transaction(function () use ($request, $project, $data) {
            $current = CommunityProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->authorizeChat($request, $current);
            abort_unless(in_array($current->status, ['recruiting', 'scheduled', 'active'], true)
                && ($current->status !== 'active' || $current->project_close_at->isFuture()), 409);
            $message = $current->messages()->create(['user_id' => $request->user()->id, 'message' => trim($data['message'])]);

            return response()->json(['message' => [
                'id' => $message->id, 'user' => $request->user()->name, 'message' => $message->message,
                'created_at' => $message->created_at->toIso8601String(),
            ]], 201);
        });
    }

    private function authorizeChat(Request $request, CommunityProject $project): void
    {
        $this->ensureParticipant($request->user(), $project);
        if (in_array($project->status, CommunityProject::RUNNING_STATUSES, true)) {
            $this->ensureGroupMember($request->user(), $project);
        }
    }

    private function ensureParticipant(?User $user, CommunityProject $project): void
    {
        abort_unless($user && $project->participants()->where('user_id', $user->id)->exists(), 403);
    }

    private function ensureGroupMember(?User $user, CommunityProject $project): void
    {
        abort_unless($user && CommunityGroupMember::where('community_group_id', $project->community_group_id)
            ->where('user_id', $user->id)->exists(), 403, __('Diese Aktion ist nur für Gruppenmitglieder verfügbar.'));
    }
}
