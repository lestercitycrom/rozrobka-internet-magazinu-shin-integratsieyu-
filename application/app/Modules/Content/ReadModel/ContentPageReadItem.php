<?php

namespace App\Modules\Content\ReadModel;

final readonly class ContentPageReadItem
{
    public function __construct(public string $slug, public string $title, public ?string $excerpt, public ?string $body, public ?string $metaTitle, public ?string $metaDescription, public ?array $hero = null) {}
}
