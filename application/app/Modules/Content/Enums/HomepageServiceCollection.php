<?php

namespace App\Modules\Content\Enums;

enum HomepageServiceCollection: string
{
    case Additional = 'additional';
    case Wheels = 'wheels';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $collection): string => $collection->value, self::cases());
    }
}
