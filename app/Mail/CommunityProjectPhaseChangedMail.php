<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommunityProjectPhaseChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?string $imagePath = null,
        public ?string $imageAlt = null,
        public string $emailLocale = 'en',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.community.project-phase',
            text: 'emails.community.project-phase-text',
            with: [
                'body' => $this->body,
                'imagePath' => $this->imagePath,
                'imageAlt' => $this->imageAlt,
                'emailLocale' => $this->emailLocale,
            ],
        );
    }
}
