<?php

namespace App\Support;

final class PublicEditorialUrl
{
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $parts = parse_url($url);

            return is_array($parts)
                && ! isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'])
                ? $url
                : null;
        }

        return PublicHttpUrl::sanitize($url);
    }

    public static function isValid(mixed $value): bool
    {
        return self::sanitize($value) !== null;
    }

    public static function isExternal(mixed $value): bool
    {
        return PublicHttpUrl::isValid($value);
    }

    public static function localized(mixed $value, string $locale): ?string
    {
        $url = self::sanitize($value);
        if ($url === null || self::isExternal($url) || $locale !== 'ru') {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path !== '/ru' && ! str_starts_with($path, '/ru/')) {
            $path = '/ru'.($path === '/' ? '' : $path);
        }

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
