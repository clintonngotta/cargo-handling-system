<?php

declare(strict_types=1);

namespace App\Application\Cargo\Data;

final readonly class IngestionReport
{
    public function __construct(
        public int $recorded,
        public int $duplicates,
        public SettlementReport $settlement,
    ) {}
}
