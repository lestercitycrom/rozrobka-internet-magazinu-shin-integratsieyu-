<?php

namespace App\Modules\Payments\Dintero;

use App\Modules\Payments\Exceptions\PaymentOperationRejected;

final readonly class DinteroConfiguration
{
    public string $environment;

    public string $accountId;

    public string $clientId;

    public string $clientSecret;

    public string $profileId;

    public string $apiBaseUrl;

    public int $connectTimeout;

    public int $timeout;

    public function __construct()
    {
        $environment = strtolower(trim((string) config('payments.dintero.environment', 'test')));
        if (! in_array($environment, ['test', 'production'], true)) {
            throw new PaymentOperationRejected('Dintero environment must be test or production.');
        }

        $accountKey = $environment === 'production' ? 'production_account_id' : 'test_account_id';
        $accountId = trim((string) config("payments.dintero.{$accountKey}"));
        $expectedPrefix = $environment === 'production' ? 'P' : 'T';
        if (preg_match('/^'.$expectedPrefix.'\d{8}$/', $accountId) !== 1) {
            throw new PaymentOperationRejected("Dintero {$environment} account ID is missing or has the wrong environment prefix.");
        }

        $clientId = trim((string) config('payments.dintero.client_id'));
        $clientSecret = trim((string) config('payments.dintero.client_secret'));
        if ($clientId === '' || $clientSecret === '') {
            throw new PaymentOperationRejected('Dintero client credentials are not configured.');
        }
        if ((bool) config('payments.dintero.require_callback_signature', true)
            && trim((string) config('payments.dintero.callback_secret')) === '') {
            throw new PaymentOperationRejected('Dintero callback signature protection is required but has no secret.');
        }

        $profileId = trim((string) config('payments.dintero.profile_id', 'default'));
        if ($profileId === '' || mb_strlen($profileId) > 255) {
            throw new PaymentOperationRejected('Dintero payment profile is invalid.');
        }

        $apiBaseUrl = rtrim((string) config('payments.dintero.api_base_url', 'https://api.dintero.com/v1'), '/');
        if (! str_starts_with($apiBaseUrl, 'https://')) {
            throw new PaymentOperationRejected('Dintero API must use HTTPS.');
        }

        $this->environment = $environment;
        $this->accountId = $accountId;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->profileId = $profileId;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->connectTimeout = max(1, (int) config('payments.dintero.connect_timeout', 3));
        $this->timeout = max($this->connectTimeout, (int) config('payments.dintero.timeout', 10));
    }

    public function accountBaseUrl(): string
    {
        return "{$this->apiBaseUrl}/accounts/{$this->accountId}";
    }
}
