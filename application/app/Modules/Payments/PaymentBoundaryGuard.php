<?php

namespace App\Modules\Payments;

use App\Modules\NdiApi\NdiIntegrationProfile;
use App\Modules\NdiApi\NdiSandboxContract;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Exceptions\PaymentOperationRejected;
use App\Modules\Payments\Exceptions\PaymentsDisabled;

final class PaymentBoundaryGuard
{
    public static function assertEnabled(): void
    {
        if (! config('payments.enabled') && ! app()->runningUnitTests()) {
            throw new PaymentsDisabled('Payments remain disabled until a reviewed provider adapter is configured.');
        }
    }

    public static function assertOrderAllowed(Order $order): void
    {
        $dinteroTest = (string) config('payments.provider') === 'dintero'
            && strtolower(trim((string) config('payments.dintero.environment', 'test'))) === 'test';
        $sandboxOrder = $order->is_integration_test
            || $order->integration_profile === NdiIntegrationProfile::SANDBOX;

        // A sandbox order must never cross into a live payment environment, even if it
        // was created earlier while staging test switches were enabled.
        if (! $dinteroTest) {
            if ($sandboxOrder) {
                throw new PaymentOperationRejected('The isolated integration fixture is restricted to Dintero test mode.');
            }

            return;
        }
        if (app()->environment('production')) {
            throw new PaymentOperationRejected('Dintero test payments are forbidden in production.');
        }

        $order->loadMissing(['items', 'serviceItems']);
        $line = $order->items->count() === 1 ? $order->items->first() : null;
        if (! $order->is_integration_test
            || $order->integration_profile !== NdiIntegrationProfile::SANDBOX
            || $line === null
            || $order->serviceItems->isNotEmpty()
            || $line->external_sku !== NdiSandboxContract::CATALOG_SKU
            || $line->supplierArticleId() !== NdiSandboxContract::ARTICLE_ID
            || $line->quantity !== NdiSandboxContract::QUANTITY
            || $line->unit_price_minor !== NdiSandboxContract::UNIT_PRICE_MINOR
            || $line->line_total_minor !== NdiSandboxContract::TOTAL_MINOR
            || $order->subtotal_minor !== NdiSandboxContract::TOTAL_MINOR
            || $order->total_minor !== NdiSandboxContract::TOTAL_MINOR
            || $order->currency !== NdiSandboxContract::CURRENCY) {
            throw new PaymentOperationRejected('Dintero test mode accepts only the approved isolated integration fixture.');
        }
    }

    public static function provider(string $provider): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $provider) !== 1 || $provider === 'disabled') {
            throw new PaymentOperationRejected('A configured payment provider is required.');
        }

        return $provider;
    }

    public static function idempotencyKey(string $key): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key) !== 1) {
            throw new PaymentOperationRejected('A canonical UUID idempotency key is required.');
        }

        return strtolower($key);
    }

    public static function money(int $amountMinor, string $currency): string
    {
        $currency = strtoupper($currency);
        if ($amountMinor < 1 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new PaymentOperationRejected('A positive amount and valid currency are required.');
        }

        return $currency;
    }

    public static function providerReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 255) {
            throw new PaymentOperationRejected('The provider returned an invalid payment reference.');
        }

        return $reference;
    }
}
