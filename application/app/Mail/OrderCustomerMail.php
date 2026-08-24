<?php

namespace App\Mail;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCustomerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('customer_mail.subject_'.$this->event, ['reference' => $this->order->public_reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.orders.customer-status');
    }
}
