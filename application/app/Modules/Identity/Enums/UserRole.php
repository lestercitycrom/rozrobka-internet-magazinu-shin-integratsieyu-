<?php

namespace App\Modules\Identity\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Operations = 'operations';
    case Customer = 'customer';

    /** @return list<string> */
    public static function adminValues(): array
    {
        return [self::Owner->value, self::Editor->value, self::Operations->value];
    }

    public function canAccessAdmin(): bool
    {
        return $this !== self::Customer;
    }
}
