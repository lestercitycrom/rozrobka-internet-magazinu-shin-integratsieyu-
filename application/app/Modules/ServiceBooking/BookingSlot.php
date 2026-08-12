<?php

namespace App\Modules\ServiceBooking;

use Carbon\CarbonImmutable;

final readonly class BookingSlot
{
    public function __construct(
        public int $intervalId,
        public string $key,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $remaining,
    ) {}

    /** @return array{key: string, label: string, starts_at: string, ends_at: string, remaining: int} */
    public function toPublicArray(string $timezone): array
    {
        $start = $this->startsAt->setTimezone($timezone);
        $end = $this->endsAt->setTimezone($timezone);

        return [
            'key' => $this->key,
            'label' => $start->format('H:i').'–'.$end->format('H:i'),
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'remaining' => $this->remaining,
        ];
    }
}
