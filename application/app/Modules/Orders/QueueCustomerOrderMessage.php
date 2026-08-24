<?php

namespace App\Modules\Orders;

use App\Mail\OrderCustomerMail;
use App\Modules\Orders\Models\CustomerCommunication;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Mail;

final class QueueCustomerOrderMessage
{
    public function execute(Order $order, string $event): void
    {
        $order = Order::query()->with(['items', 'serviceItems', 'paymentAttempt'])->findOrFail($order->getKey());
        $version = $order->terms_version ?: LegalDocumentVersions::TERMS;
        $communication = CustomerCommunication::query()->firstOrCreate(
            ['order_id' => $order->id, 'event' => $event, 'document_version' => $version],
            [
                'recipient' => mb_strtolower(trim($order->customer_email)),
                'locale' => $order->locale,
                'prepared_at' => now(),
            ],
        );
        if (! config('customer_communications.delivery_enabled', false) || $communication->dispatched_at !== null) {
            return;
        }

        Mail::to($communication->recipient)
            ->locale($communication->locale)
            ->queue((new OrderCustomerMail($order, $event))->locale($communication->locale));
        $communication->update(['dispatched_at' => now()]);
    }
}
