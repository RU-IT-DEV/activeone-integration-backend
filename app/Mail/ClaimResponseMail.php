<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\ClaimResponse;
use Illuminate\Mail\Mailables\Attachment;

class ClaimResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $claimId;

    public $claimResponse;
    public $approvedAmount;
    public $rejectedAmount;
    public $status;
    public $email_version;
    public $claim_type;
    public $pdfContent; 
    public $logoUrl;
    

    /**
     * Create a new message instance.
     */
    public function __construct($claimId, $claimResponse, $approvedAmount, $rejectedAmount, $status, $email_version, $claim_type, $pdfContent = null)
    {
        $this->claimId = $claimId;
        $this->claimResponse = $claimResponse;
        $this->approvedAmount = $approvedAmount;
        $this->rejectedAmount = $rejectedAmount;
        $this->status = $status;
        $this->email_version = $email_version;
        $this->claim_type = $claim_type;
        $this->pdfContent = $pdfContent; // nullable
        $this->logoUrl = "https://storage.cloud.google.com/medflex-plus-development/flexicare-logo.png";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubject(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        switch ($this->email_version) {
            case 'ULSSI':
                return new Content(
                    markdown: 'emails.ulssi-claim-response',
                    with: [
                        'claimId' => $this->claimId,
                        'claimResponse' => $this->claimResponse,
                        'approvedAmount' => $this->approvedAmount,
                        'rejectedAmount' => $this->rejectedAmount,
                        'status' => $this->status,
                        'logoUrl' => $this->logoUrl,
                    ],
                );
        
            case 'SHELL':
                return new Content(
                    markdown: 'emails.shell-claim-response',
                    with: [
                        'claimId' => $this->claimId,
                        'claimResponse' => $this->claimResponse,
                        'approvedAmount' => $this->approvedAmount,
                        'rejectedAmount' => $this->rejectedAmount,
                        'status' => $this->status,
                        'claim_type' => $this->claim_type,
                        'logoUrl' => $this->logoUrl,
                    ],
                );
            default:
                // No email will be sent if version does not match
                return null;
        }
        
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        switch ($this->email_version) {
            case 'ULSSI':
                return [];
            case 'SHELL':

                if ($this->pdfContent && $this->status !== 'Rejected') {
                    $claim_id = $this->claimId;
                    $type   = $this->claim_type;
                
                    // Format claim type
                    if ($type === 'choicepot') {
                        $typeLabel = 'CHOICE POT';
                    } elseif ($type === 'fsa') {
                        $typeLabel = 'FSA';
                    } else {
                        $typeLabel = strtoupper($type); // fallback
                    }
                    // Format processed date (you can change format)
                    $processedDate = now()->format('Y-m-d');
                
                    // Build filename
                    $filename = "{$typeLabel} - Claim # {$claim_id} - {$processedDate}.pdf";
                
                    return [
                        Attachment::fromData(function () {
                            return $this->pdfContent;
                        }, $filename)->withMime('application/pdf'),
                    ];
                }
            default:
                return [];
        }
        return [];
    }

    private function getSubject()
    {
        switch ($this->status) {
            case 'Approved':
                return 'Claim Submission: Approved ('.$this->claimId.')';
            case 'Partially approved':
                return 'Claim Submission: Partially Approved ('.$this->claimId.')';
            case 'Rejected':
                return 'Claim Submission: Disapproved ('.$this->claimId.')';
            default:
                return 'Claim Submission: Update';
        }
    }
}
