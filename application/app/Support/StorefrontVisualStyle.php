<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StorefrontVisualStyle
{
    public const SOFT = 'soft';

    public const SHARP = 'sharp';

    public function __construct(private readonly Request $request) {}

    public function value(): string
    {
        $sharpHost = mb_strtolower(trim((string) config('storefront.sharp_host')));

        if ($sharpHost !== '' && mb_strtolower($this->request->getHost()) === $sharpHost) {
            return self::SHARP;
        }

        return self::SOFT;
    }

    public function isSharp(): bool
    {
        return $this->value() === self::SHARP;
    }

    public function canonicalUrl(string $url): string
    {
        if (! $this->isSharp()) {
            return $url;
        }

        $primaryUrl = rtrim((string) config('app.url'), '/');

        if (filter_var($primaryUrl, FILTER_VALIDATE_URL) === false) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $primaryUrl.'/'.ltrim($path, '/');
    }
}
