<?php

namespace App\Modules\Content\ReadModel;

final readonly class ServicePageReadItem
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public ?string $body,
        public ?string $seoTitle,
        public ?string $seoDescription, public ?array $hero = null,
    ) {}
}
