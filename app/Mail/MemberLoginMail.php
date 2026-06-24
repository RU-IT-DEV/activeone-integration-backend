<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public $signInLink, $logoUrl, $email, $name;
    /**
     * Create a new message instance.
     */
    public function __construct($signInLink, $email, $name)
    {
        $this->signInLink = $signInLink;
        $this->email = $email;
        $this->name = $name;
        $this->logoUrl = "https://storage.cloud.google.com/medflex-plus-development/flexicare-logo.png";
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Log-In Request',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.member_login_mail',
            with: [
                'name' => $this->name,
                'signInLink' => $this->signInLink,
                'email' => $this->email,
                'logoUrl' => $this->logoUrl,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
