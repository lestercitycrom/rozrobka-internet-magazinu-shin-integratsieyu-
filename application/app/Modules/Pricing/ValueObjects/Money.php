<?php

namespace App\Modules\Pricing\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter uppercase code.');
        }
    }

    public static function fromDecimal(string $amount, string $currency): self
    {
        if (preg_match('/^(\d{1,12})(?:\.(\d{1,4}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid decimal money amount.');
        }

        $fraction = str_pad($matches[2] ?? '', 4, '0');
        $minor = ((int) $matches[1] * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $minor++;
        }

        return new self($minor, strtoupper($currency));
    }

    public function addMinor(int $minor): self
    {
        $result = $this->minor + $minor;
        if ($result < 0) {
            throw new InvalidArgumentException('Money adjustment cannot create a negative amount.');
        }

        return new self($result, $this->currency);
    }

    public function addBasisPoints(int $basisPoints): self
    {
        $absolute = abs($basisPoints);
        $adjustment = intdiv(($this->minor * $absolute) + 5000, 10000);

        return $this->addMinor($basisPoints < 0 ? -$adjustment : $adjustment);
    }

    public function roundUpTo(int $incrementMinor): self
    {
        if ($incrementMinor < 1) {
            throw new InvalidArgumentException('Rounding increment must be positive.');
        }

        $rounded = intdiv($this->minor + $incrementMinor - 1, $incrementMinor) * $incrementMinor;

        return new self($rounded, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return new self($this->minor * $quantity, $this->currency);
    }

    public function decimal(): string
    {
        return intdiv($this->minor, 100).'.'.str_pad((string) ($this->minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
