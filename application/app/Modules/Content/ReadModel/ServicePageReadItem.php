<?php

namespace App\Modules\Content\ReadModel;

use App\Modules\Content\Enums\ServiceRequestFlow;

final readonly class ServicePageReadItem
{
    public function __construct(
        public string $slug,
        public ?string $canonicalPath,
        public ServiceRequestFlow $requestFlow,
        public string $title,
        public ?string $excerpt,
        public ?string $body,
        public ?string $seoTitle,
        public ?string $seoDescription, public ?array $hero = null,
    ) {}
}
