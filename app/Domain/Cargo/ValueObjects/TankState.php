<?php

declare(strict_types=1);

namespace App\Domain\Cargo\ValueObjects;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Exceptions\CommodityMismatchException;
use App\Domain\Cargo\Exceptions\InsufficientTankBalanceException;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;

/**
 * Immutable snapshot of a shore tank. An instance that breaks the capacity
 * limit cannot exist, so every path that builds one enforces the rule.
 */
final readonly class TankState
{
    public function __construct(
        public string $tankCode,
        public Commodity $commodity,
        public Litres $capacity,
        public Litres $volume,
    ) {
        if ($volume->isGreaterThan($capacity)) {
            throw TankCapacityExceededException::attempted($tankCode, $capacity, $volume);
        }
    }

    public function receive(Commodity $commodity, Litres $quantity): self
    {
        $this->assertHolds($commodity);

        return $this->withVolume($this->volume->plus($quantity));
    }

    public function dispatch(Commodity $commodity, Litres $quantity): self
    {
        $this->assertHolds($commodity);

        if ($quantity->isGreaterThan($this->volume)) {
            throw InsufficientTankBalanceException::attempted($this->tankCode, $this->volume, $quantity);
        }

        return $this->withVolume($this->volume->minus($quantity));
    }

    public function available(): Litres
    {
        return $this->capacity->minus($this->volume);
    }

    private function withVolume(Litres $volume): self
    {
        return new self($this->tankCode, $this->commodity, $this->capacity, $volume);
    }

    private function assertHolds(Commodity $commodity): void
    {
        if ($commodity !== $this->commodity) {
            throw CommodityMismatchException::attempted($this->tankCode, $this->commodity, $commodity);
        }
    }
}
