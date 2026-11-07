<?php

namespace App\Http\Controllers;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\CommunityProjectParticipant;
use App\Models\User;
use App\Services\Community\ProjectLifecycle;
use App\Services\Community\ProjectPhaseNotifier;
use App\Services\Community\ProjectTemplate;
use App\Services\Community\WallReservationMutex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    public function reservations(Request $request, WallReservationMutex $mutex)
    {
        $area = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'min:1', 'max:100000'],
            'height' => ['required', 'integer', 'min:1', 'max:100000'],
            'check_locks' => ['sometimes', 'boolean'],
        ]);
        $groupId = $request->user()?->communityMembership()->value('community_group_id');
        $groupId = $groupId === null ? null : (int) $groupId;

        $reservations = CommunityProject::query()
            ->where(function ($query) {
                $query->whereIn('status', ['scheduled', 'active'])
                    ->orWhere(fn ($query) => $query
                        ->where('status', 'published')
                        ->where('reserved_until', '>', now()));
            })
            ->whereNotNull('reserved_x')->whereNotNull('reserved_y')
            ->with('group:id,name')
            ->get(['id', 'community_group_id', 'width', 'height', 'reserved_x', 'reserved_y', 'project_close_at', 'scheduled_start_at', 'reserved_until', 'status'])
            ->filter(fn (CommunityProject $project) => $project->reserved_x < $area['x'] + $area['width']
                && $project->reserved_x + $project->width > $area['x']
                && $project->reserved_y < $area['y'] + $area['height']
                && $project->reserved_y + $project->height > $area['y'])
            ->map(fn (CommunityProject $project) => [
                'id' => $project->id,
                'group_id' => $project->community_group_id,
                'group' => $project->group->name,
                'x' => (int) $project->reserved_x,
                'y' => (int) $project->reserved_y,
                'width' => (int) $project->width,
                'height' => (int) $project->height,
                'days_remaining' => max(0, (int) ceil((($project->status === 'published'
                    ? $project->reserved_until : ($project->project_close_at ?? $project->scheduled_start_at->copy()->addDays(7)))->timestamp - now()->timestamp) / 86400)),
                'published' => $project->status === 'published',
                'is_member' => $groupId === (int) $project->community_group_id,
                'project_url' => route('community.projects.show', $project),
            ]);

        $result = ['reservations' => $reservations];
        if ($request->boolean('check_locks')) {
            $result['locked_pixels'] = $mutex->activePixelLocks($area['x'], $area['y'], $area['width'], $area['height']);
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request, ProjectLifecycle $lifecycle)
    {
        $groupId = $request->user()?->communityMembership()->value('community_group_id');
        if ($groupId) {
            foreach (CommunityProject::where('community_group_id', $groupId)->whereIn('status', CommunityProject::RUNNING_STATUSES)->get() as $runningProject) {
                $lifecycle->refresh($runningProject);
            }
        }
        $membership = $request->user()?->communityMembership()
            ->with(['group' => fn ($query) => $query->withCount([
                'projects as active_projects_count' => fn ($query) => $query->whereIn('status', CommunityProject::RUNNING_STATUSES),
                'projects as completed_projects_count' => fn ($query) => $query->where('status', 'published'),
            ])])
            ->first();
        $activeGroupMembersCount = 0;
        $currentProject = null;
        $currentProjects = collect();
        if ($membership) {
            $membership->forceFill(['last_active_at' => now()])->save();
            $activeGroupMembersCount = CommunityGroupMember::query()
                ->where('community_group_id', $membership->community_group_id)
                ->where('last_active_at', '>=', now()->subMinutes(5))
                ->count();
            $currentProjects = $membership->group->projects()
                ->whereIn('status', CommunityProject::RUNNING_STATUSES)
                ->with(['participants' => fn ($query) => $query->where('user_id', $request->user()->id)])
                ->latest('updated_at')
                ->get();
            $currentProject = $currentProjects->first();
        }

        $groups = CommunityGroup::query()
            ->where('status', 'approved')
            ->when($membership, fn ($query) => $query->where('id', '!=', $membership->community_group_id))
            ->withCount([
                'members',
                'projects as active_projects_count' => fn ($query) => $query->whereIn('status', CommunityProject::RUNNING_STATUSES),
                'projects as completed_projects_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->with('founder')
            ->orderBy('name')
            ->paginate(24);
        $latestPublishedProjects = CommunityProject::query()
            ->where('status', 'published')
            ->whereNotNull('image_path')
            ->with('group')
            ->latest('published_at')
            ->limit(3)
            ->get();
        $showcaseExamples = collect();
        if ($latestPublishedProjects->isEmpty()) {
            $exampleGroups = CommunityGroup::query()
                ->where('status', 'approved')
                ->whereIn('emblem', ['retro-riot', 'cozy-pixels', 'neon-district'])
                ->get()
                ->keyBy('emblem');
            $showcaseExamples = collect([
                ['title' => 'Pixel Sunset', 'emblem' => 'cozy-pixels', 'image' => 'pixel-sunset.svg'],
                ['title' => 'Arcade Hero', 'emblem' => 'retro-riot', 'image' => 'arcade-hero.svg'],
                ['title' => 'Neon Nights', 'emblem' => 'neon-district', 'image' => 'neon-nights.svg'],
            ])->map(fn (array $example) => $example + ['group' => $exampleGroups->get($example['emblem'])])
                ->filter(fn (array $example) => $example['group'] !== null)
                ->values();
        }

        return view('community.index', [
            'groups' => $groups,
            'latestPublishedProjects' => $latestPublishedProjects,
            'showcaseExamples' => $showcaseExamples,
            'membership' => $membership,
            'activeGroupMembersCount' => $activeGroupMembersCount,
            'currentProject' => $currentProject,
            'currentProjects' => $currentProjects,
            'isCurrentProjectParticipant' => $currentProject?->participants->isNotEmpty() ?? false,
            'hasFoundedGroup' => $request->user()?->foundedCommunityGroup()->exists() ?? false,
            'lastLeftGroupId' => $request->user()?->last_left_community_group_id,
            'groupSwitchAvailableAt' => $request->user()?->community_group_left_at?->copy()->addDays(7),
        ]);
    }

    public function gallery(CommunityGroup $group)
    {
        abort_unless($group->status === 'approved', 404);

        $projects = $group->projects()
            ->where('status', 'published')
            ->whereNotNull('image_path')
            ->latest('published_at')
            ->paginate(24);

        return view('community.groups.gallery', compact('group', 'projects'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->foundedCommunityGroup()->exists()) {
            throw ValidationException::withMessages(['group' => __('Du hast deine einmalige Gruppengründung bereits genutzt.')]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:60', 'unique:community_groups,name'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $group = CommunityGroup::create($data + ['founder_id' => $user->id]);

        return redirect()->route('community.groups.show', $group)->with('status', __('Dein Gruppenantrag wurde zur Prüfung eingereicht.'));
    }

    public function show(Request $request, CommunityGroup $group, ProjectLifecycle $lifecycle)
    {
        abort_unless(
            $group->status === 'approved' || $group->founder_id === $request->user()?->id,
            404
        );

        $group->load('founder')->loadCount([
            'members',
            'projects as active_projects_count' => fn ($query) => $query->whereIn('status', CommunityProject::RUNNING_STATUSES),
            'projects as completed_projects_count' => fn ($query) => $query->where('status', 'published'),
        ]);
        $projects = $group->projects()
            ->withExists(['participants as is_participant' => fn ($query) => $query->where('user_id', $request->user()?->id)])
            ->orderByRaw("CASE WHEN status IN ('recruiting', 'scheduled', 'active') THEN 0 ELSE 1 END")
            ->latest()->paginate(12);
        $projects->setCollection($projects->getCollection()->map(function (CommunityProject $project) use ($lifecycle) {
            $updated = $lifecycle->refresh($project);
            $updated->setAttribute('is_participant', $project->is_participant);

            return $updated;
        }));

        $membership = $request->user()?->communityMembership()->first();
        if ($membership?->community_group_id === $group->id) {
            $membership->forceFill(['last_active_at' => now()])->save();
        }

        return view('community.groups.show', [
            'group' => $group,
            'projects' => $projects,
            'membership' => $membership,
            'runningProjectCount' => $group->projects()->whereIn('status', CommunityProject::RUNNING_STATUSES)->count(),
            'hasOwnRunningProject' => $request->user() && CommunityProject::occupiedBy($request->user()->id),
            'activeGroupMembersCount' => CommunityGroupMember::query()
                ->where('community_group_id', $group->id)
                ->where('last_active_at', '>=', now()->subMinutes(5))
                ->count(),
            'lastLeftGroupId' => $request->user()?->last_left_community_group_id,
            'groupSwitchAvailableAt' => $request->user()?->community_group_left_at?->copy()->addDays(7),
        ]);
    }

    public function join(Request $request, CommunityGroup $group)
    {
        abort_unless($group->status === 'approved', 404);
        $request->validate([
            'confirm_group_switch_cooldown' => ['accepted'],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($user, $group) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->communityMembership()->exists()) {
                throw ValidationException::withMessages(['group' => __('Du gehörst bereits einer Gruppe an. Verlasse sie zuerst, bevor du einer anderen beitrittst.')]);
            }
            if (
                $lockedUser->community_group_left_at?->copy()->addDays(7)->isFuture()
                && (int) $lockedUser->last_left_community_group_id !== (int) $group->id
            ) {
                throw ValidationException::withMessages([
                    'group' => __('Du kannst erst ab :date einer anderen Gruppe beitreten. In deine zuletzt verlassene Gruppe kannst du sofort zurückkehren.', [
                        'date' => $lockedUser->community_group_left_at->copy()->addDays(7)->format('d.m.Y H:i'),
                    ]),
                ]);
            }

            CommunityGroupMember::create([
                'community_group_id' => $group->id,
                'user_id' => $user->id,
            ]);
        });

        return back()->with('status', __('Du bist der Gruppe beigetreten.'));
    }

    public function leave(Request $request, ProjectPhaseNotifier $phaseNotifier, ProjectLifecycle $lifecycle)
    {
        $userId = $request->user()->id;
        $leaveResult = DB::transaction(function () use ($userId) {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $membership = CommunityGroupMember::query()->where('user_id', $userId)->first();
            if (! $membership) {
                return ['left' => false, 'cancelled_project_ids' => []];
            }

            $allProjectIds = CommunityProject::query()
                ->where('community_group_id', $membership->community_group_id)
                ->pluck('id');
            $activeProjectIds = CommunityProject::query()
                ->whereIn('id', $allProjectIds)
                ->whereIn('status', CommunityProject::RUNNING_STATUSES)
                ->pluck('id');
            CommunityProject::whereIn('id', $activeProjectIds)->orderBy('id')->lockForUpdate()->get();
            CommunityProjectParticipant::query()
                ->whereIn('community_project_id', $activeProjectIds)
                ->where('user_id', $membership->user_id)
                ->delete();
            $cancelledProjects = CommunityProject::query()
                ->whereIn('id', $activeProjectIds)
                ->where('status', 'active')->whereDoesntHave('participants')
                ->get();
            foreach ($cancelledProjects as $cancelledProject) {
                $cancelledProject->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'no_participants',
                    'cancelled_at' => now(),
                ]);
            }
            $user->forceFill([
                'last_left_community_group_id' => $membership->community_group_id,
                'community_group_left_at' => now(),
            ])->save();
            $membership->delete();

            return ['left' => true, 'cancelled_project_ids' => $cancelledProjects->modelKeys(), 'running_project_ids' => $activeProjectIds];
        });
        if (! $leaveResult['left']) {
            return back();
        }
        foreach ($leaveResult['cancelled_project_ids'] as $projectId) {
            $phaseNotifier->notify(CommunityProject::findOrFail($projectId));
        }
        foreach (CommunityProject::whereIn('id', $leaveResult['running_project_ids'])->get() as $runningProject) {
            $lifecycle->refresh($runningProject);
        }

        return redirect()->route('community.index')->with('status', __('Du hast die Gruppe verlassen.'));
    }

    public function createProject(Request $request, CommunityGroup $group, ProjectPhaseNotifier $phaseNotifier, ProjectLifecycle $lifecycle)
    {
        $this->ensureMember($request->user(), $group);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:100'],
            'type' => ['required', Rule::in(['template', 'free'])],
            'width' => ['required', 'integer', 'min:32', 'max:200'],
            'height' => ['required', 'integer', 'min:32', 'max:200'],
            'minimum_participants' => ['required', 'integer', 'min:3', 'max:1000'],
            'expected_duration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'scheduled_start_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays(7)->toIso8601String()],
            'image' => ['nullable', 'required_if:type,template', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        if ($request->hasFile('image')) {
            app(ProjectTemplate::class)->validateCrop(
                $request->file('image')->getPathname(), $data['width'], $data['height'], 0, 0);
        }
        $lifecycle->refreshForUser($request->user()->id);
        foreach ($group->projects()->whereIn('status', CommunityProject::RUNNING_STATUSES)->get() as $runningProject) {
            $lifecycle->refresh($runningProject);
        }

        $project = DB::transaction(function () use ($request, $group, $data) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->ensureMember($request->user(), $group);
            CommunityGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->projects()->whereIn('status', CommunityProject::RUNNING_STATUSES)->count() >= CommunityProject::MAX_RUNNING) {
                throw ValidationException::withMessages(['project' => __('Diese Gruppe hat bereits fünf laufende Projekte.')]);
            }
            if (CommunityProject::occupiedBy($request->user()->id)) {
                throw ValidationException::withMessages(['project' => __('Du leitest bereits ein Projekt oder bist für eines angemeldet.')]);
            }

            $project = CommunityProject::create([
                'community_group_id' => $group->id,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'type' => $data['type'],
                'width' => $data['width'],
                'height' => $data['height'],
                'status' => 'recruiting',
                'recruitment_close_at' => now()->addHours(24),
                'proposals_close_at' => now(),
                'minimum_participants' => $data['minimum_participants'],
                'expected_duration_hours' => $data['expected_duration_hours'],
                'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
            ]);

            CommunityProjectParticipant::create([
                'community_project_id' => $project->id,
                'user_id' => $request->user()->id,
            ]);
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('community-templates', 'local');
                if (! $path) {
                    throw new \RuntimeException('Project template could not be stored.');
                }
                $project->update(['template_image_path' => $path, 'crop_x' => 0, 'crop_y' => 0]);
            }

            return $project;
        });

        $phaseNotifier->notify($project);

        return redirect()->route('community.groups.show', $group)->with('status', __('Die Anmeldung läuft 24 Stunden. Sobald genug Mitglieder dabei sind, startet das Projekt sofort oder zum geplanten Zeitpunkt.'));
    }

    private function ensureMember(User $user, CommunityGroup $group): void
    {
        abort_unless(
            $group->members()->where('user_id', $user->id)->exists(),
            403,
            __('Diese Aktion ist nur für Gruppenmitglieder verfügbar.')
        );
    }
}
