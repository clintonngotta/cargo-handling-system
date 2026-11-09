<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

final class InvalidMovementLogException extends CargoDomainException
{
    public static function invalidField(string $field, mixed $value): self
    {
        return new self(sprintf(
            'Movement log has an invalid "%s": %s.',
            $field,
            is_scalar($value) ? (string) $value : get_debug_type($value),
        ));
    }

    public static function unknownTank(string $tankCode): self
    {
        return new self(sprintf('Movement log references unknown tank "%s".', $tankCode));
    }
}
