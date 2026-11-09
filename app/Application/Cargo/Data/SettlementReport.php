<?php

declare(strict_types=1);

namespace App\Application\Cargo\Data;

final readonly class SettlementReport
{
    public function __construct(
        public int $applied = 0,
        public int $rejected = 0,
    ) {}

    public function merge(self $other): self
    {
        return new self($this->applied + $other->applied, $this->rejected + $other->rejected);
    }
}
