<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

use App\Domain\Cargo\Enums\Commodity;

final class CommodityMismatchException extends CargoDomainException
{
    public static function attempted(string $tankCode, Commodity $held, Commodity $attempted): self
    {
        return new self(sprintf(
            'Tank %s holds %s and cannot handle %s.',
            $tankCode,
            $held->value,
            $attempted->value,
        ));
    }

    public static function tankNotEmpty(string $tankCode): self
    {
        return new self(sprintf('Tank %s must be empty before its commodity can change.', $tankCode));
    }
}
