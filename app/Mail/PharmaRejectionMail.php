<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class PharmaRejectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reference_number = "", $order_datetime = "";

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
        public $rejection_reason_message
    ) {
        $this->order_datetime = $this->order->created_at->format('F d, Y h:i A');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $oRefLen = strlen("ORD-XXXXXX");
        $ref = (string) $this->order->id;
        $refLen = strlen($ref);
        $reference_number = substr("ORD-XXXXXX", 0, ($oRefLen - $refLen)) . $ref;
        $this->reference_number = $reference_number;
        return new Envelope(
            subject: "ActiveOne RX Order {$reference_number} - Unable to Process",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pharma-rejection-mail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
