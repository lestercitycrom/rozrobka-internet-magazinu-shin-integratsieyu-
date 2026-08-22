<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiConnectionSettings;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\Exceptions\NdiTransportException;

final class NdiIntegrationProfileResolver
{
    public function forInquiry(string $profile): NdiConnectionSettings
    {
        if ($profile === NdiIntegrationProfile::SANDBOX) {
            $this->assertSandboxEnvironment();
            if (! config('ndi_api.sandbox.enabled') || ! config('ndi_api.sandbox.inquiry_enabled')) {
                throw new NdiApiDisabled('NDI sandbox inquiry is disabled.');
            }

            return $this->settings($profile, (string) config('ndi_api.sandbox.party_id'));
        }
        if ($profile !== NdiIntegrationProfile::LIVE) {
            throw new NdiApiDisabled('Unknown NDI integration profile.');
        }
        if (! config('ndi_api.inquiry_enabled', false)) {
            throw new NdiApiDisabled('NDI read-only inquiry is disabled.');
        }

        return $this->settings($profile, (string) config('ndi_api.party_id'));
    }

    public function forOrder(string $profile): NdiConnectionSettings
    {
        if ($profile === NdiIntegrationProfile::SANDBOX) {
            $this->assertSandboxEnvironment();
            if (! config('ndi_api.sandbox.enabled')
                || ! config('ndi_api.sandbox.order_enabled')
                || ! config('ndi_api.c1_test_mode')) {
                throw new NdiApiDisabled('NDI sandbox C1 submission is disabled.');
            }
            if ((string) config('ndi_api.sandbox.party_id') !== '1220000') {
                throw new NdiApiDisabled('NDI sandbox C1 requires the approved test PartyID.');
            }

            return $this->settings($profile, (string) config('ndi_api.sandbox.party_id'));
        }
        if ($profile !== NdiIntegrationProfile::LIVE) {
            throw new NdiApiDisabled('Unknown NDI integration profile.');
        }
        if (! config('ndi_api.order_enabled', false)) {
            throw new NdiApiDisabled('NDI C1 order submission is disabled.');
        }

        return $this->settings($profile, (string) config('ndi_api.party_id'));
    }

    private function settings(string $profile, string $partyId): NdiConnectionSettings
    {
        $endpoint = trim((string) config('ndi_api.endpoint'));
        $username = trim((string) config('ndi_api.username'));
        $password = (string) config('ndi_api.password');
        $agencyCode = trim((string) config('ndi_api.agency_code'));
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new NdiTransportException('NDI endpoint must use HTTPS.');
        }
        if ($endpoint === '' || $username === '' || $password === '' || $partyId === '' || $agencyCode === '') {
            throw new NdiTransportException('NDI connection profile is incomplete.');
        }

        return new NdiConnectionSettings(
            profile: $profile,
            endpoint: $endpoint,
            username: $username,
            password: $password,
            partyId: $partyId,
            agencyCode: $agencyCode,
            connectTimeoutSeconds: (int) config('ndi_api.connect_timeout_seconds', 3),
            timeoutSeconds: (int) config('ndi_api.timeout_seconds', 8),
        );
    }

    private function assertSandboxEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new NdiApiDisabled('NDI sandbox traffic is forbidden in production.');
        }
    }
}
