<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

final class ImmutableMovementException extends CargoDomainException
{
    public static function alreadySettled(string $reference): self
    {
        return new self(sprintf('Movement %s is settled and can no longer change.', $reference));
    }

    public static function notRejected(string $reference): self
    {
        return new self(sprintf('Movement %s is no longer rejected, so it cannot be retried.', $reference));
    }
}
