<?php

declare(strict_types=1);

namespace Tests\Feature\Cargo;

use App\Application\Cargo\Actions\SettlePendingMovements;
use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Exceptions\CommodityMismatchException;
use App\Domain\Cargo\Exceptions\ImmutableMovementException;
use App\Domain\Cargo\Exceptions\TankCapacityExceededException;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityStateHooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direct_write_past_capacity_is_blocked(): void
    {
        $tank = StorageTank::factory()->create(['capacity_litres' => 1_000]);
        $tank->current_volume_litres = 1_001;

        $this->expectException(TankCapacityExceededException::class);

        $tank->save();
    }

    public function test_capacity_cannot_be_reduced_below_the_current_volume(): void
    {
        $tank = StorageTank::factory()->holding(500)->create(['capacity_litres' => 1_000]);
        $tank->capacity_litres = 499;

        $this->expectException(TankCapacityExceededException::class);

        $tank->save();
    }

    public function test_a_non_empty_tank_cannot_change_commodity(): void
    {
        $tank = StorageTank::factory()->holding(500)->create();
        $tank->commodity = Commodity::CrudePalmOlein;

        $this->expectException(CommodityMismatchException::class);

        $tank->save();
    }

    public function test_an_empty_tank_can_change_commodity(): void
    {
        $tank = StorageTank::factory()->create();

        $tank->update(['commodity' => Commodity::CrudePalmOlein]);

        $this->assertSame(Commodity::CrudePalmOlein, $tank->fresh()->commodity);
    }

    public function test_transit_loss_is_derived_on_save_for_every_creation_path(): void
    {
        $movement = CargoMovement::factory()->create(['gross_litres' => 200_000]);

        $this->assertSame(1_000, $movement->transit_loss_litres);
        $this->assertSame(199_000, $movement->net_litres);

        $movement->update(['gross_litres' => 100_000]);

        $this->assertSame(99_500, $movement->net_litres);
    }

    public function test_outbound_movements_carry_no_transit_loss(): void
    {
        $movement = CargoMovement::factory()->create([
            'direction' => MovementDirection::Outbound,
            'gross_litres' => 200_000,
        ]);

        $this->assertSame(0, $movement->transit_loss_litres);
        $this->assertSame(200_000, $movement->net_litres);
    }

    public function test_a_settled_movement_cannot_be_edited(): void
    {
        $movement = $this->settledMovement();

        $this->expectException(ImmutableMovementException::class);

        $movement->update(['gross_litres' => 1]);
    }

    public function test_a_settled_movement_cannot_be_deleted(): void
    {
        $movement = $this->settledMovement();

        $this->expectException(ImmutableMovementException::class);

        $movement->delete();
    }

    private function settledMovement(): CargoMovement
    {
        $movement = CargoMovement::factory()->create();

        app(SettlePendingMovements::class)->handle();

        return $movement->fresh();
    }
}
