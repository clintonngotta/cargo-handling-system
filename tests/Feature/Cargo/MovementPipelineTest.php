<?php

declare(strict_types=1);

namespace Tests\Feature\Cargo;

use App\Application\Cargo\Actions\IngestMovementLogs;
use App\Application\Cargo\Actions\SettlePendingMovements;
use App\Application\Cargo\Data\IngestionReport;
use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\InvalidMovementLogException;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_arriving_out_of_order_are_settled_chronologically(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);

        // The delivery arrives first but happened last. Arrival order would reject it for lack of stock.
        $report = $this->ingest([
            $this->log('truck-1', 'outbound', 50_000, '2026-11-01T12:00:00Z'),
            $this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z'),
        ]);

        $this->assertSame(2, $report->settlement->applied);
        $this->assertSame(0, $report->settlement->rejected);
        $this->assertSame(49_500, $tank->fresh()->current_volume_litres);
        $this->assertSame(99_500, $this->movement('vessel-1')->balance_after_litres);
        $this->assertSame(49_500, $this->movement('truck-1')->balance_after_litres);
    }

    public function test_timestamps_with_different_offsets_are_compared_in_utc(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);

        // 09:00+03:00 is 06:00 UTC, which is before the 07:00 UTC delivery.
        $this->ingest([
            $this->log('truck-1', 'outbound', 1_000, '2026-11-01T07:00:00+00:00'),
            $this->log('vessel-1', 'inbound', 100_000, '2026-11-01T09:00:00+03:00'),
        ]);

        $this->assertSame(98_500, $tank->fresh()->current_volume_litres);
    }

    public function test_capacity_is_checked_against_the_net_inbound_quantity(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1', 'capacity_litres' => 99_500]);

        $this->ingest([$this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z')]);

        $this->assertSame(99_500, $tank->fresh()->current_volume_litres);
        $this->assertSame(500, $this->movement('vessel-1')->transit_loss_litres);
    }

    public function test_an_overflow_rejects_only_that_movement_and_leaves_the_balance_intact(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1', 'capacity_litres' => 150_000]);

        $report = $this->ingest([
            $this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z'),
            $this->log('vessel-2', 'inbound', 100_000, '2026-11-01T09:00:00Z'),
            $this->log('truck-1', 'outbound', 10_000, '2026-11-01T10:00:00Z'),
        ]);

        $this->assertSame(2, $report->settlement->applied);
        $this->assertSame(1, $report->settlement->rejected);
        $this->assertSame(89_500, $tank->fresh()->current_volume_litres);

        $rejected = $this->movement('vessel-2');
        $this->assertSame(MovementStatus::Rejected, $rejected->status);
        $this->assertStringContainsString('cannot hold', (string) $rejected->rejection_reason);
        $this->assertNull($rejected->balance_after_litres);
    }

    public function test_a_redelivered_log_does_not_change_the_balance_twice(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);
        $log = $this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z');

        $this->ingest([$log]);
        $report = $this->ingest([$log]);

        $this->assertSame(0, $report->recorded);
        $this->assertSame(1, $report->duplicates);
        $this->assertSame(99_500, $tank->fresh()->current_volume_litres);
        $this->assertSame(1, CargoMovement::query()->count());
    }

    public function test_settling_again_finds_nothing_left_to_apply(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1']);
        $this->ingest([$this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z')]);

        $report = app(SettlePendingMovements::class)->handle();

        $this->assertSame(0, $report->applied);
        $this->assertSame(99_500, $tank->fresh()->current_volume_litres);
    }

    public function test_a_commodity_mismatch_is_rejected(): void
    {
        $tank = StorageTank::factory()->create(['code' => 'T-1', 'commodity' => Commodity::CrudePalmOil]);

        $this->ingest([[...$this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z'), 'commodity' => 'PFAD']]);

        $this->assertSame(MovementStatus::Rejected, $this->movement('vessel-1')->status);
        $this->assertSame(0, $tank->fresh()->current_volume_litres);
    }

    public function test_an_invalid_log_fails_the_batch_before_anything_is_written(): void
    {
        StorageTank::factory()->create(['code' => 'T-1']);

        try {
            $this->ingest([
                $this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z'),
                $this->log('vessel-2', 'inbound', -5, '2026-11-01T09:00:00Z'),
            ]);
            $this->fail('Expected the batch to be rejected.');
        } catch (InvalidMovementLogException) {
            $this->assertSame(0, CargoMovement::query()->count());
        }
    }

    public function test_an_unknown_tank_fails_the_batch_before_anything_is_written(): void
    {
        $this->expectException(InvalidMovementLogException::class);

        $this->ingest([$this->log('vessel-1', 'inbound', 100_000, '2026-11-01T08:00:00Z')]);
    }

    /** @param list<array<string, mixed>> $logs */
    private function ingest(array $logs): IngestionReport
    {
        return app(IngestMovementLogs::class)->handle($logs);
    }

    private function movement(string $reference): CargoMovement
    {
        return CargoMovement::query()->where('external_reference', $reference)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function log(string $reference, string $direction, int $litres, string $occurredAt): array
    {
        return [
            'reference' => $reference,
            'direction' => $direction,
            'commodity' => 'CPO',
            'tank_code' => 'T-1',
            'counterparty' => $direction === 'inbound' ? 'MV Example' : 'KCA 123A',
            'litres' => $litres,
            'occurred_at' => $occurredAt,
        ];
    }
}
