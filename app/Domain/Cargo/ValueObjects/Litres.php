<?php

declare(strict_types=1);

namespace App\Domain\Cargo\ValueObjects;

use App\Domain\Cargo\Exceptions\InvalidQuantityException;

/**
 * A non-negative volume in whole litres. Integers keep balances free of
 * floating-point drift across millions of litres.
 */
final readonly class Litres
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw InvalidQuantityException::negative($value);
        }
    }

    public function plus(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function minus(self $other): self
    {
        return new self($this->value - $other->value);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }
}
