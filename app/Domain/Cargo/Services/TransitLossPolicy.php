<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Services;

use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\ValueObjects\AdjustedQuantity;
use App\Domain\Cargo\ValueObjects\Litres;

/**
 * Inbound vessel flows lose 0.5% in transit. Outbound flows are unadjusted.
 * The loss is rounded half-up to a whole litre, and net is derived by
 * subtraction so no litre goes unaccounted for.
 */
final class TransitLossPolicy
{
    public const int INBOUND_LOSS_BASIS_POINTS = 50;

    private const int BASIS_POINTS_DIVISOR = 10_000;

    public function assess(MovementDirection $direction, Litres $gross): AdjustedQuantity
    {
        $loss = new Litres(match ($direction) {
            MovementDirection::Inbound => $this->inboundLoss($gross),
            MovementDirection::Outbound => 0,
        });

        return new AdjustedQuantity($gross, $loss, $gross->minus($loss));
    }

    private function inboundLoss(Litres $gross): int
    {
        return intdiv(
            $gross->value * self::INBOUND_LOSS_BASIS_POINTS + intdiv(self::BASIS_POINTS_DIVISOR, 2),
            self::BASIS_POINTS_DIVISOR,
        );
    }
}
