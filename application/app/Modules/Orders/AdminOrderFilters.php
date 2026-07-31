<?php

namespace App\Modules\Orders;

use App\Modules\Orders\Enums\NdiOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;

/** Safe normalized query filters for the read-only admin order list. */
final readonly class AdminOrderFilters
{
    public function __construct(
        public ?OrderStatus $status,
        public ?PaymentStatus $payment,
        public ?NdiOrderStatus $ndi,
        public ?string $query,
        public ?string $from,
        public ?string $to,
    ) {}

    /** @param array<string, mixed> $input */
    public static function from(array $input): self
    {
        return new self(
            self::enum($input['status'] ?? null, OrderStatus::class),
            self::enum($input['payment'] ?? null, PaymentStatus::class),
            self::enum($input['ndi'] ?? null, NdiOrderStatus::class),
            self::text($input['q'] ?? null), self::date($input['from'] ?? null), self::date($input['to'] ?? null),
        );
    }

    /** @template T of \BackedEnum @param class-string<T> $class @return ?T */
    private static function enum(mixed $value, string $class): ?\BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Invalid order filter.');
        }

        return $class::tryFrom($value) ?? throw new \InvalidArgumentException('Invalid order filter.');
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && ($value = trim($value)) !== '' ? mb_substr($value, 0, 120) : null;
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        } if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException('Invalid order filter.');
        }

        return $value;
    }
}
