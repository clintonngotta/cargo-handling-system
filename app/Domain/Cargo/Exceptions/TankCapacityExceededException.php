<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

use App\Domain\Cargo\ValueObjects\Litres;

final class TankCapacityExceededException extends CargoDomainException
{
    public static function attempted(string $tankCode, Litres $capacity, Litres $volume): self
    {
        return new self(sprintf(
            'Tank %s cannot hold %s L: its capacity is %s L.',
            $tankCode,
            number_format($volume->value),
            number_format($capacity->value),
        ));
    }
}
