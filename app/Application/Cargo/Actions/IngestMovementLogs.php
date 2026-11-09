<?php

declare(strict_types=1);

namespace App\Application\Cargo\Actions;

use App\Application\Cargo\Data\IngestionReport;
use App\Application\Cargo\Data\MovementLogData;
use App\Domain\Cargo\Exceptions\InvalidMovementLogException;
use App\Models\CargoMovement;
use App\Models\StorageTank;

/**
 * Pipeline entry point: validates a batch, records it idempotently, then
 * settles it. Any invalid log fails the batch before anything is written.
 */
final readonly class IngestMovementLogs
{
    public function __construct(private SettlePendingMovements $settle) {}

    /** @param iterable<array<string, mixed>> $rawLogs */
    public function handle(iterable $rawLogs): IngestionReport
    {
        $logs = [];

        foreach ($rawLogs as $rawLog) {
            $logs[] = MovementLogData::fromArray($rawLog);
        }

        $tankIds = $this->resolveTankIds($logs);
        $recorded = 0;

        foreach ($logs as $log) {
            // A unique index on the reference makes redelivery safe, even from a concurrent consumer.
            $movement = CargoMovement::query()->createOrFirst(
                ['external_reference' => $log->reference],
                [
                    'direction' => $log->direction,
                    'commodity' => $log->commodity,
                    'storage_tank_id' => $tankIds[$log->tankCode],
                    'counterparty' => $log->counterparty,
                    'gross_litres' => $log->grossQuantity->value,
                    'occurred_at' => $log->occurredAt,
                ],
            );

            $recorded += $movement->wasRecentlyCreated ? 1 : 0;
        }

        return new IngestionReport(
            recorded: $recorded,
            duplicates: count($logs) - $recorded,
            settlement: $this->settle->handle(array_values(array_unique($tankIds))),
        );
    }

    /**
     * @param  list<MovementLogData>  $logs
     * @return array<string, int>
     */
    private function resolveTankIds(array $logs): array
    {
        $codes = array_values(array_unique(array_map(
            static fn (MovementLogData $log): string => $log->tankCode,
            $logs,
        )));

        $tankIds = StorageTank::query()->whereIn('code', $codes)->pluck('id', 'code')->all();

        foreach ($codes as $code) {
            if (! isset($tankIds[$code])) {
                throw InvalidMovementLogException::unknownTank($code);
            }
        }

        return $tankIds;
    }
}
