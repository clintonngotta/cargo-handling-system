<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

final class InvalidQuantityException extends CargoDomainException
{
    public static function negative(int $value): self
    {
        return new self(sprintf('A quantity cannot be negative: got %d L.', $value));
    }
}
