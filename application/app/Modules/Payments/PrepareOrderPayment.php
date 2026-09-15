<?php

namespace App\Modules\Payments;

use App\Modules\NdiApi\ConfirmNdiAvailability;
use App\Modules\NdiApi\Enums\NdiAvailabilityStatus;
use App\Modules\NdiApi\Exceptions\NdiAvailabilityNotConfirmed;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\TransitionOrder;
use App\Modules\Payments\Contracts\OrderPaymentReadiness;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Models\PaymentAttempt;

final class PrepareOrderPayment
{
    public function __construct(
        private readonly OrderPaymentReadiness $readiness,
        private readonly ConfirmNdiAvailability $availability,
        private readonly TransitionOrder $transition,
        private readonly AuthorizePayment $authorize,
    ) {}

    public function execute(Order $order, string $returnUrl, ?string $callbackUrl = null, ?string $termsUrl = null): PaymentAttempt
    {
        $order = Order::query()->findOrFail($order->getKey());
        PaymentBoundaryGuard::assertOrderAllowed($order);
        if (in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            return $this->authorize->execute($order, PaymentIdempotencyKey::authorizeFor($order), $returnUrl, $callbackUrl, $termsUrl);
        }
        if ($order->payment_status !== PaymentStatus::NotStarted
            || ! in_array($order->status, [OrderStatus::PendingReview, OrderStatus::AwaitingPayment], true)) {
            throw new PaymentOperationRejected('The order cannot start a new payment lifecycle.');
        }

        $this->readiness->assertReady($order);
        $check = $this->availability->execute($order, 'checkout');
        if ($check->status !== NdiAvailabilityStatus::Confirmed) {
            throw new NdiAvailabilityNotConfirmed('NDI did not confirm every requested quantity.');
        }

        if ($order->status === OrderStatus::PendingReview) {
            $order = $this->transition->execute($order, OrderStatus::AwaitingPayment);
        }

        return $this->authorize->execute($order, PaymentIdempotencyKey::authorizeFor($order), $returnUrl, $callbackUrl, $termsUrl);
    }
}
