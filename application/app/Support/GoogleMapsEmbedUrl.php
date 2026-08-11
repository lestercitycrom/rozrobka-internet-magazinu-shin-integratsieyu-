<?php

namespace App\Support;

final class GoogleMapsEmbedUrl
{
    private const ALLOWED_HOSTS = [
        'www.google.com',
        'maps.google.com',
        'www.google.no',
        'maps.google.no',
    ];

    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https'
            || ! in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || ($path !== '/maps/embed' && ! str_starts_with($path, '/maps/embed/'))
        ) {
            return null;
        }

        return $url;
    }
}
