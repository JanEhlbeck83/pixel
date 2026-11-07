<?php

namespace App\Http\Controllers;

use App\Models\CommunityProject;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityPhaseUpdateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_if($request->user()->isBanned(), 403);

        $projects = CommunityProject::query()
            ->whereHas('group.members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withExists(['participants as viewer_is_participant' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderBy('id')
            ->get()
            ->map(function (CommunityProject $project) use ($request) {
                $key = match ($project->status) {
                    'recruiting' => 'Für „:title“ werden mindestens drei Teilnehmende gesucht.',
                    'scheduled' => 'Der Start für „:title“ ist geplant.',
                    'active' => 'Die gemeinsame Gestaltung von „:title“ hat begonnen.',
                    'published' => '„:title“ wurde veröffentlicht.',
                    'cancelled' => '„:title“ wurde abgebrochen.',
                    'archived' => '„:title“ wurde archiviert.',
                };

                $participantCount = $project->status === 'recruiting' ? $project->participantCount() : 0;
                $needsParticipants = $project->status === 'recruiting'
                    && $project->created_at->lte(now()->subMinutes(3))
                    && $project->recruitment_close_at?->isFuture()
                    && $participantCount < $project->minimum_participants
                    && ! CommunityProject::occupiedBy($request->user()->id)
                    && $project->created_by !== $request->user()->id
                    && ! $project->viewer_is_participant;

                return [
                    'id' => $project->id,
                    'status' => $project->status,
                    'message' => __($key, ['title' => $project->title]),
                    'url' => Locales::url(app()->getLocale(), '/community/projects/'.$project->id),
                    'group_url' => Locales::url(app()->getLocale(), '/community/groups/'.$project->community_group_id),
                    'recruitment_reminder' => $needsParticipants,
                    'recruitment_message' => $needsParticipants
                        ? __('„:title“ sucht noch :count Teilnehmende. Mach mit und starte das Projekt gemeinsam mit deiner Gruppe!', [
                            'title' => $project->title,
                            'count' => $project->minimum_participants - $participantCount,
                        ]) : null,
                    'join_url' => ! $project->viewer_is_participant && $project->isOpenForJoining()
                        && ! CommunityProject::occupiedBy($request->user()->id)
                        ? Locales::url(app()->getLocale(), '/community/projects/'.$project->id).'#projectJoinForm' : null,
                ];
            });

        return response()->json(['user_id' => $request->user()->id, 'projects' => $projects])
            ->header('Cache-Control', 'private, no-store');
    }
}
