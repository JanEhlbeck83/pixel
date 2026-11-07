<?php

namespace App\Services\Community;

use App\Mail\CommunityProjectPhaseChangedMail;
use App\Models\CommunityProject;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProjectPhaseNotifier
{
    public function notify(CommunityProject $project): void
    {
        $project->loadMissing('group');
        $recipientIds = $project->group->members()->pluck('user_id')
            ->merge($project->participants()->pluck('user_id'))
            ->unique();
        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->whereNotNull('email_verified_at')
            ->whereNull('banned_at')
            ->where('project_phase_notifications', true)
            ->where(fn ($query) => $query->whereNull('last_active_at')
                ->orWhere('last_active_at', '<=', now()->subMinutes(5)))
            ->get(['id', 'email', 'preferred_locale']);

        foreach ($recipients as $recipient) {
            $locale = Locales::isSupported($recipient->preferred_locale)
                ? $recipient->preferred_locale
                : Locales::default();
            [$subject, $body, $imagePath, $imageAlt] = $this->message($project, $locale);

            try {
                Mail::to($recipient->email)->send(new CommunityProjectPhaseChangedMail($subject, $body, $imagePath, $imageAlt, $locale));
            } catch (Throwable $exception) {
                Log::error('Community project phase notification could not be sent.', [
                    'project_id' => $project->id,
                    'user_id' => $recipient->id,
                    'locale' => $locale,
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function message(CommunityProject $project, string $locale): array
    {
        $subjectKey = match ($project->status) {
            'published' => 'Euer Gruppenprojekt „:title“ ist veröffentlicht!',
            'cancelled' => 'Update zu eurem Gruppenprojekt „:title“',
            default => 'Neues zu deinem Gruppenprojekt bei PixRiot',
        };
        $subject = Lang::get($subjectKey, ['title' => $project->title], $locale);
        $deadline = match ($project->status) {
            'recruiting' => $project->recruitment_close_at,
            'scheduled' => $project->scheduled_start_at,
            'active' => $project->project_close_at,
            'published' => $project->reserved_until,
            default => null,
        };
        $deadlineText = $deadline?->format('d.m.Y H:i') ?? '';
        $key = match ($project->status) {
            'recruiting' => 'Für „:title“ läuft die Anmeldung bis :deadline. Sobald genug Mitglieder dabei sind, startet das Projekt sofort oder zum geplanten Zeitpunkt.',
            'scheduled' => 'Die Anmeldung für „:title“ war erfolgreich. Der gemeinsame Start ist am :deadline.',
            'active' => '„:title“ ist gestartet. Die Projektleitung muss das Projekt bis :deadline abschließen; danach verfällt es.',
            'published' => 'Das Gruppenprojekt „:title“ wurde veröffentlicht und ist jetzt auf der Wall. Die Fläche bleibt bis :deadline für die Gruppe reserviert.',
            default => $this->cancellationMessage($project),
        };

        $body = Lang::get($key, [
            'title' => $project->title,
            'deadline' => $deadlineText,
        ], $locale);
        $url = Locales::url($locale, '/community/projects/'.$project->id);
        $imagePath = $project->status === 'published' && $project->image_path
            && Storage::disk('public')->exists($project->image_path)
            ? Storage::disk('public')->path($project->image_path)
            : null;
        $imageAlt = $imagePath
            ? Lang::get('Veröffentlichtes Gruppenprojekt: :title', ['title' => $project->title], $locale)
            : null;

        return [
            $subject,
            $body."\n\n".Lang::get('Projekt ansehen: :url', ['url' => $url], $locale)
                ."\n\n".Lang::get('Projektphasen-E-Mails kannst du in deinen Projekteinstellungen abbestellen: :url', [
                    'url' => Locales::url($locale, '/profile').'#projectNotificationsTitle',
                ], $locale),
            $imagePath,
            $imageAlt,
        ];
    }

    private function cancellationMessage(CommunityProject $project): string
    {
        return match ($project->cancellation_reason) {
            'recruitment_expired', 'insufficient_participants' => '„:title“ ist verfallen, weil nicht genügend Mitglieder angemeldet waren.',
            'starter_cancelled' => 'Das Gruppenprojekt „:title“ wurde von der Projektleitung vorzeitig abgebrochen. Entwürfe und Projektpixel bleiben erhalten.',
            'completion_expired' => '„:title“ wurde nicht innerhalb von sieben Tagen abgeschlossen und ist verfallen. Die Fläche ist wieder frei.',
            'leader_left' => '„:title“ wurde abgebrochen, weil die Projektleitung die Gruppe verlassen hat.',
            'no_participants' => 'Das Gruppenprojekt „:title“ wurde abgebrochen, weil keine Teilnehmenden mehr dabei waren. Die Fläche ist wieder frei.',
            default => 'Das Gruppenprojekt „:title“ wurde abgebrochen.',
        };
    }
}
