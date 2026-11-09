<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Services\TransitLossPolicy;
use App\Domain\Cargo\ValueObjects\Litres;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransitLossPolicyTest extends TestCase
{
    /** @return array<string, array{int, int, int}> */
    public static function inboundQuantities(): array
    {
        return [
            'exact half percent' => [100_000, 500, 99_500],
            'half litre rounds up' => [100, 1, 99],
            'below half a litre rounds to zero' => [99, 0, 99],
            'one million' => [1_000_000, 5_000, 995_000],
        ];
    }

    #[DataProvider('inboundQuantities')]
    public function test_inbound_flow_loses_half_a_percent(int $gross, int $loss, int $net): void
    {
        $quantity = (new TransitLossPolicy)->assess(MovementDirection::Inbound, new Litres($gross));

        $this->assertSame($loss, $quantity->loss->value);
        $this->assertSame($net, $quantity->net->value);
    }

    public function test_outbound_flow_has_no_transit_loss(): void
    {
        $quantity = (new TransitLossPolicy)->assess(MovementDirection::Outbound, new Litres(100_000));

        $this->assertSame(0, $quantity->loss->value);
        $this->assertSame(100_000, $quantity->net->value);
    }
}
