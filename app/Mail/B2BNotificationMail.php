<?php

namespace App\Mail;

use App\Models\NotificationMailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2BNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly NotificationMailLog $log,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->log->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.b2b-notification',
            with: [
                'bodyHtml' => $this->log->body_html,
            ],
        );
    }
}
