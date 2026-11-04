<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class NewsletterMail extends Mailable
{
    public function __construct(public Newsletter $newsletter, public ?User $recipient = null) {}

    public function unsubscribeUrl(): ?string
    {
        return $this->recipient
            ? URL::signedRoute('newsletter.unsubscribe', ['user' => $this->recipient->id])
            : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->newsletter->from_email, $this->newsletter->from_name),
            replyTo: $this->newsletter->reply_to ? [new Address($this->newsletter->reply_to)] : [],
            subject: $this->newsletter->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            with: ['newsletter' => $this->newsletter, 'unsubscribeUrl' => $this->unsubscribeUrl()],
        );
    }

    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: $url ? [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ] : []);
    }
}
