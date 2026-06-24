<?php

namespace App\Mail;

use App\Http\Controllers\Api\FileSystemController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Auth;

class ClaimFilingMail extends Mailable
{
    use Queueable, SerializesModels;

    public $claim, $myUrl, $logoUrl, $support_sentence;
    /**
     * Create a new message instance.
     */
    public function __construct($claim)
    {
        $this->claim = $claim;
        // $this->myUrl = config('app.backend_url');
        $filesysmtem = new FileSystemController;
        $company = Auth::user()->company;
        $logo_path = $company->logo_path;
        
        $support_sentence_template = $company->support_email_sentence_template;
        $arr_supports = $company->support;
        $support_emails = $arr_supports->pluck('email')->map(function ($email) {
            return "<b>$email</b>";
        })->toArray();

        if (str_contains($support_sentence_template, "{{emails}}")) {
            $str_support_emails = implode(", ", $support_emails);
            $this->support_sentence = str_replace("{{emails}}", $str_support_emails, $support_sentence_template);
        } else if (str_contains($support_sentence_template, "{{emails0}}")) {
            $str_support_sentence = $support_sentence_template;
            foreach ($support_emails as $key => $value) {
                $str_support_sentence = str_replace("{{emails{$key}}}", $value, $str_support_sentence);
            }
            $this->support_sentence = $str_support_sentence;
        } else if (str_contains($support_sentence_template, "{{bentype.email}}")) {
            $str_support_sentence = $support_sentence_template;
            $support_email = $arr_supports->where('label', $this->claim->type)->first();
            if ($support_email) {
                $str_support_sentence = str_replace("{{bentype.email}}", "<b>".$support_email->email."</b>", $support_sentence_template);
                $str_support_sentence = str_replace("{{bentype}}", "<b>".$support_email->label."</b>", $str_support_sentence);
            }
            $this->support_sentence = $str_support_sentence;
        }
        $object = $filesysmtem->getGSObject($logo_path);
        $expiresAt = new \DateTime('tomorrow');
        $this->logoUrl = $object->signedUrl($expiresAt);
        // $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Claim Submission: Successful ('.$this->claim->claim_id.')'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.claim_filing_mail',
            with: [
                'claim' => $this->claim,
                'support' => $this->support_sentence,
                // 'myUrl' => $this->myUrl,
                'logoUrl' => $this->logoUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // return [
        //     Attachment::fromStorageDisk('gcs', $this->claim->receipt)
        //         ->as('receipt')
        // ];
        return [];
    }
}
