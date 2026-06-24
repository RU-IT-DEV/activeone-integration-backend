<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScanQRAcknowledgementMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $claimId;
    public string $receiveDate;
    public string $employeeName;

    public string $logoUrl;

    public function __construct(string $claimId, string $receiveDate, string $employeeName)
    {
        $this->claimId = $claimId;
        $this->receiveDate = $receiveDate;
        $this->employeeName = $employeeName;
        $this->logoUrl = "https://storage.cloud.google.com/medflex-plus-development/flexicare-logo.png";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Acknowledgement of Claim Documents Received",
        );
    }
    
    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.scan-qr-acknowledgement-mail',
            with: [
                'claimId' => $this->claimId,
                'receiveDate' => $this->receiveDate,
                'employeeName' => $this->employeeName,
                'logoUrl' => $this->logoUrl,
            ]
        );
    }
}
