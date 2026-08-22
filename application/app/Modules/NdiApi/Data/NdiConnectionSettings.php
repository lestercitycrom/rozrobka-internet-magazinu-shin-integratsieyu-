<?php

namespace App\Modules\NdiApi\Data;

final readonly class NdiConnectionSettings
{
    public function __construct(
        public string $profile,
        public string $endpoint,
        public string $username,
        public string $password,
        public string $partyId,
        public string $agencyCode,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
    ) {}
}
