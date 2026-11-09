<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

use App\Domain\Cargo\ValueObjects\Litres;

final class InsufficientTankBalanceException extends CargoDomainException
{
    public static function attempted(string $tankCode, Litres $balance, Litres $requested): self
    {
        return new self(sprintf(
            'Tank %s cannot deliver %s L: its balance is %s L.',
            $tankCode,
            number_format($requested->value),
            number_format($balance->value),
        ));
    }
}
