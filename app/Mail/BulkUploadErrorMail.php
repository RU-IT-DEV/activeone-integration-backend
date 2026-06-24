<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkUploadErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $publicUrl;
    protected $errorRows;

    protected $logoUrl = "https://storage.cloud.google.com/medflex-plus-development/flexicare-logo.png";

    /**
     * Create a new message instance.
     */
    public function __construct($publicUrl, $errorRows)
    {
        $this->publicUrl = $publicUrl;
        $this->errorRows = $errorRows;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bulk Upload Error Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function build()
    {
        
        return $this->subject('Bulk Upload Completed With Errors')
            ->markdown('emails.bulk_upload_error_mail', [
                'logoUrl' => config('app.logo_url'),
                'publicUrl' => $this->publicUrl,
                'errorRows' => $this->errorRows,
            ]);
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
