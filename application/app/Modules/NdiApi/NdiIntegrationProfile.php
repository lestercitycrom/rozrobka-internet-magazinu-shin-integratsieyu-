<?php

namespace App\Modules\NdiApi;

final class NdiIntegrationProfile
{
    public const LIVE = 'ndi_live';

    public const SANDBOX = 'ndi_sandbox';

    public static function isKnown(string $profile): bool
    {
        return in_array($profile, [self::LIVE, self::SANDBOX], true);
    }
}
