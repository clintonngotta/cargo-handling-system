<?php

declare(strict_types=1);

namespace App\Application\Cargo\Data;

use App\Domain\Cargo\ValueObjects\AdjustedQuantity;
use App\Domain\Cargo\ValueObjects\Litres;

final readonly class MovementPreview
{
    public function __construct(
        public AdjustedQuantity $quantity,
        public ?Litres $balanceAfter,
        public ?string $problem,
    ) {}
}
