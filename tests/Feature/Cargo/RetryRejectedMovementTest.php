<?php

declare(strict_types=1);

namespace Tests\Feature\Cargo;

use App\Application\Cargo\Actions\IngestMovementLogs;
use App\Application\Cargo\Actions\RetryRejectedMovement;
use App\Application\Cargo\Actions\SettlePendingMovements;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\ImmutableMovementException;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetryRejectedMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_delivery_rejected_for_lack_of_stock_applies_once_stock_arrives(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);

        $this->ingest('truck-1', 'outbound', 50_000);
        $delivery = CargoMovement::query()->where('external_reference', 'truck-1')->firstOrFail();
        $this->assertSame(MovementStatus::Rejected, $delivery->status);

        $this->ingest('vessel-1', 'inbound', 100_000);
        $retried = app(RetryRejectedMovement::class)->handle($delivery);

        $this->assertSame(MovementStatus::Applied, $retried->status);
        $this->assertNull($retried->rejection_reason);
        $this->assertSame(49_500, $retried->balance_after_litres);
        $this->assertSame(49_500, $tank->fresh()->current_volume_litres);
    }

    public function test_a_retry_that_still_breaks_a_rule_stays_rejected(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);

        $this->ingest('truck-1', 'outbound', 50_000);
        $delivery = CargoMovement::query()->where('external_reference', 'truck-1')->firstOrFail();

        $retried = app(RetryRejectedMovement::class)->handle($delivery);

        $this->assertSame(MovementStatus::Rejected, $retried->status);
        $this->assertNotNull($retried->rejection_reason);
        $this->assertSame(0, $tank->fresh()->current_volume_litres);
    }

    public function test_an_applied_movement_cannot_be_retried_from_a_stale_copy(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);

        $this->ingest('truck-1', 'outbound', 50_000);
        $staleCopy = CargoMovement::query()->where('external_reference', 'truck-1')->firstOrFail();
        $this->ingest('vessel-1', 'inbound', 100_000);
        app(RetryRejectedMovement::class)->handle($staleCopy);

        try {
            app(RetryRejectedMovement::class)->handle($staleCopy);
            $this->fail('Expected the second retry to be refused.');
        } catch (ImmutableMovementException) {
            $this->assertSame(49_500, $tank->fresh()->current_volume_litres);
        }
    }

    public function test_requeueing_cannot_be_used_to_change_a_rejected_quantity(): void
    {
        StorageTank::factory()->create(['code' => 'T-1']);
        $this->ingest('truck-1', 'outbound', 50_000);
        $delivery = CargoMovement::query()->where('external_reference', 'truck-1')->firstOrFail();

        $delivery->gross_litres = 1;

        $this->expectException(ImmutableMovementException::class);

        $delivery->requeue();
    }

    public function test_an_applied_movement_cannot_return_to_pending(): void
    {
        $movement = CargoMovement::factory()->create(['direction' => MovementDirection::Inbound]);
        app(SettlePendingMovements::class)->handle();

        $this->expectException(ImmutableMovementException::class);

        $movement->fresh()->requeue();
    }

    private function ingest(string $reference, string $direction, int $litres): void
    {
        app(IngestMovementLogs::class)->handle([[
            'reference' => $reference,
            'direction' => $direction,
            'commodity' => 'CPO',
            'tank_code' => 'T-1',
            'counterparty' => 'Example',
            'litres' => $litres,
            'occurred_at' => '2026-11-01T08:00:00Z',
        ]]);
    }
}
