<?php

namespace App\Modules\Payments;

use App\Modules\NdiApi\ConfirmNdiAvailability;
use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\NdiApi\Enums\NdiSubmissionStatus;
use App\Modules\NdiApi\Exceptions\NdiAvailabilityNotConfirmed;
use App\Modules\NdiApi\Exceptions\NdiOrderNotReady;
use App\Modules\NdiApi\SubmitNdiOrder;
use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Models\PaymentAttempt;
use Throwable;

final class FinalizeOrderPayment
{
    public function __construct(
        private readonly OrderPaymentReadiness $readiness,
        private readonly ConfirmNdiAvailability $availability,
        private readonly SubmitNdiOrder $submit,
        private readonly VoidAuthorizedPayment $void,
    ) {}

    public function execute(Order $order): PaymentAttempt
    {
        $order = Order::query()->findOrFail($order->getKey());
        if ($order->payment_status === PaymentStatus::Paid) {
            return PaymentAttempt::query()->where('order_id', $order->id)->firstOrFail();
        }
        if ($order->payment_status !== PaymentStatus::Authorized) {
            throw new PaymentOperationRejected('An authorized payment is required before supplier submission.');
        }
        if (in_array($order->ndi_status, [NdiOrderStatus::Submitted, NdiOrderStatus::Unknown], true)) {
            throw new NdiOrderNotReady('The supplier outcome is uncertain and requires manual reconciliation.');
        }
        if ($order->ndi_status === NdiOrderStatus::Rejected) {
            $this->void->execute($order, PaymentIdempotencyKey::voidFor($order));
            throw new NdiOrderNotReady('NDI rejected the order; the payment authorization was voided.');
        }
        if ($order->ndi_status === NdiOrderStatus::Accepted) {
            return PaymentAttempt::query()->where('order_id', $order->id)->firstOrFail();
        }

        try {
            $this->readiness->assertReady($order);
            $check = $this->availability->execute($order, 'checkout');
            if ($check->status !== NdiAvailabilityStatus::Confirmed) {
                throw new NdiAvailabilityNotConfirmed('NDI did not reconfirm every requested quantity.');
            }
            $submission = $this->submit->execute($order);
        } catch (Throwable $exception) {
            $current = Order::query()->findOrFail($order->getKey());
            if (in_array($current->ndi_status, [NdiOrderStatus::NotSubmitted, NdiOrderStatus::Rejected], true)) {
                $this->void->execute($current, PaymentIdempotencyKey::voidFor($current));
            }

            throw $exception;
        }

        if ($submission->status === NdiSubmissionStatus::Accepted) {
            return PaymentAttempt::query()->where('order_id', $order->id)->firstOrFail();
        }
        if ($submission->status === NdiSubmissionStatus::Rejected) {
            $this->void->execute($order->refresh(), PaymentIdempotencyKey::voidFor($order));
            throw new NdiOrderNotReady('NDI rejected the order; the payment authorization was voided.');
        }

        throw new NdiOrderNotReady('The supplier outcome is uncertain and requires manual reconciliation.');
    }
}
