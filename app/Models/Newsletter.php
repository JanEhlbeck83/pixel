<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    public const TEMPLATES = [
        'neon' => 'Neon (dunkel, Pink)',
        'poster' => 'Poster (Pink-Header, hell)',
        'clean' => 'Clean (weiß, dezent)',
    ];

    public const LOCALES = ['de' => 'Deutsch', 'en' => 'English', 'es' => 'Español'];

    protected $fillable = [
        'subject', 'preheader', 'from_name', 'from_email', 'reply_to', 'template', 'locale', 'headline', 'body',
        'header_image', 'body_image', 'button_label', 'button_url',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /** Nur Nutzer mit Einwilligung, bestätigter Adresse und ohne Sperre. */
    public static function recipients(): Builder
    {
        return User::query()
            ->whereNotNull('newsletter_subscribed_at')
            ->whereNotNull('email_verified_at')
            ->whereNull('banned_at');
    }

    public function imageUrl(?string $path): ?string
    {
        return $path ? route('newsletter.image', ['file' => basename($path)]) : null;
    }

    public function deleteImages(): void
    {
        foreach ([$this->header_image, $this->body_image] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /** Markdown ohne rohes HTML und ohne gefährliche Links. */
    public function bodyHtml(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
