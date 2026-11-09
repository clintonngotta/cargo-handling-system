<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Exceptions\CommodityMismatchException;
use App\Domain\Cargo\Exceptions\InsufficientTankBalanceException;
use App\Domain\Cargo\Exceptions\InvalidQuantityException;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Domain\Cargo\ValueObjects\TankState;
use PHPUnit\Framework\TestCase;

class TankStateTest extends TestCase
{
    public function test_receiving_up_to_exact_capacity_is_allowed(): void
    {
        $state = $this->tank(volume: 900)->receive(Commodity::CrudePalmOil, new Litres(100));

        $this->assertSame(1_000, $state->volume->value);
        $this->assertTrue($state->available()->isZero());
    }

    public function test_receiving_past_capacity_throws_the_domain_exception(): void
    {
        $this->expectException(TankCapacityExceededException::class);
        $this->expectExceptionMessage('Tank T-1 cannot hold 1,001 L: its capacity is 1,000 L.');

        $this->tank(volume: 900)->receive(Commodity::CrudePalmOil, new Litres(101));
    }

    public function test_a_state_above_capacity_cannot_be_constructed(): void
    {
        $this->expectException(TankCapacityExceededException::class);

        $this->tank(volume: 1_001);
    }

    public function test_dispatching_reduces_the_volume_without_mutating_the_original(): void
    {
        $original = $this->tank(volume: 500);

        $this->assertSame(200, $original->dispatch(Commodity::CrudePalmOil, new Litres(300))->volume->value);
        $this->assertSame(500, $original->volume->value);
    }

    public function test_dispatching_more_than_the_balance_throws(): void
    {
        $this->expectException(InsufficientTankBalanceException::class);

        $this->tank(volume: 100)->dispatch(Commodity::CrudePalmOil, new Litres(101));
    }

    public function test_a_tank_rejects_a_different_commodity(): void
    {
        $this->expectException(CommodityMismatchException::class);

        $this->tank(volume: 0)->receive(Commodity::PalmFattyAcidDistillate, new Litres(1));
    }

    public function test_litres_cannot_be_negative(): void
    {
        $this->expectException(InvalidQuantityException::class);

        new Litres(-1);
    }

    private function tank(int $volume): TankState
    {
        return new TankState('T-1', Commodity::CrudePalmOil, new Litres(1_000), new Litres($volume));
    }
}
