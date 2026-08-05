<?php

namespace App\Modules\NdiImport;

/**
 * Normalizes the only product-image URL shape supplied in NDI's tire workbook.
 *
 * The catalog file currently contains HTTP links to files.ndias.com. We keep
 * neither arbitrary supplier URLs nor HTTP URLs in the application: accepted
 * links are reconstructed as HTTPS after validating the host, endpoint and UUID.
 */
final class NdiMediaUrl
{
    public static function canonicalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || ! self::isAllowedHost((string) ($parts['host'] ?? ''))
            || ! self::isAllowedPort($parts['port'] ?? null)
            || strcasecmp((string) ($parts['path'] ?? ''), '/Imagev2.aspx') !== 0
        ) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = is_string($query['id'] ?? null) ? strtolower($query['id']) : null;

        if ($id === null || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $id) !== 1) {
            return null;
        }

        return 'https://'.strtolower((string) $parts['host']).'/Imagev2.aspx?id='.$id;
    }

    /**
     * NDI uses the same endpoint with an empty id as an explicit "no image"
     * marker. Keep that case distinct from an unknown or malformed URL so a
     * supplier format change cannot silently remove previously valid media.
     */
    public static function isExplicitlyEmpty(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || ! self::isAllowedHost((string) ($parts['host'] ?? ''))
            || ! self::isAllowedPort($parts['port'] ?? null)
            || strcasecmp((string) ($parts['path'] ?? ''), '/Imagev2.aspx') !== 0
        ) {
            return false;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return array_keys($query) === ['id'] && $query['id'] === '';
    }

    private static function isAllowedHost(string $host): bool
    {
        $hosts = config('ndi.media_hosts', []);

        return is_array($hosts) && in_array(strtolower($host), array_map('strtolower', $hosts), true);
    }

    private static function isAllowedPort(mixed $port): bool
    {
        return $port === null || $port === 80 || $port === 443;
    }
}
