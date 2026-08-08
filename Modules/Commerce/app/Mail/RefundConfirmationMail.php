<?php

namespace Modules\Commerce\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Order\Order;

class RefundConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "İadeniz işleme alındı · {$this->order->order_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.refund-confirmation');
    }
}