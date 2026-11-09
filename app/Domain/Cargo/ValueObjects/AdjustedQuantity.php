<?php

declare(strict_types=1);

namespace App\Domain\Cargo\ValueObjects;

/**
 * A logged quantity split into what was lost in transit and what actually
 * reaches (or leaves) the tank. Invariant: gross = loss + net.
 */
final readonly class AdjustedQuantity
{
    public function __construct(
        public Litres $gross,
        public Litres $loss,
        public Litres $net,
    ) {}
}
