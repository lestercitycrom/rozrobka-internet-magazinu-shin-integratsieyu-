<?php

namespace App\Modules\Catalog;

final class CatalogDataMode
{
    public static function isLive(): bool
    {
        return config('ndi.catalog_mode', 'live') === 'live';
    }

    public static function isSnapshot(): bool
    {
        return config('ndi.catalog_mode', 'live') === 'snapshot'
            && app()->environment(['staging', 'local', 'testing']);
    }

    public static function isValid(): bool
    {
        return self::isLive() || self::isSnapshot();
    }

    public static function mode(): string
    {
        return self::isLive() ? 'live' : (self::isSnapshot() ? 'snapshot' : 'invalid');
    }
}
