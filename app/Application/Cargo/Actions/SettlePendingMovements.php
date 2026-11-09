<?php

declare(strict_types=1);

namespace App\Application\Cargo\Actions;

use App\Application\Cargo\Data\SettlementReport;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Exceptions\CargoDomainException;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Domain\Cargo\ValueObjects\TankState;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Support\Facades\DB;

/**
 * Applies pending movements to tank balances in timestamp order.
 *
 * Each tank settles in one transaction holding its row lock. Pending rows are
 * read after the lock is taken, so a second worker blocks and then finds
 * nothing left to apply. A business-rule failure rejects only that movement.
 */
final class SettlePendingMovements
{
    private const int DEADLOCK_RETRIES = 3;

    /** @param list<int>|null $tankIds Limit to these tanks. Null settles every tank with pending work. */
    public function handle(?array $tankIds = null): SettlementReport
    {
        $tankIds ??= CargoMovement::query()->pending()->distinct()->pluck('storage_tank_id')->all();

        $report = new SettlementReport;

        foreach ($tankIds as $tankId) {
            $report = $report->merge(
                DB::transaction(fn (): SettlementReport => $this->settleTank($tankId), self::DEADLOCK_RETRIES),
            );
        }

        return $report;
    }

    private function settleTank(int $tankId): SettlementReport
    {
        $tank = StorageTank::query()->lockForUpdate()->findOrFail($tankId);
        $state = $tank->state();
        $applied = 0;
        $rejected = 0;

        $movements = CargoMovement::query()
            ->where('storage_tank_id', $tankId)
            ->pending()
            ->chronological()
            ->get();

        foreach ($movements as $movement) {
            try {
                $state = $this->apply($state, $movement);
                $movement->markApplied($state->volume);
                $applied++;
            } catch (CargoDomainException $exception) {
                $movement->markRejected($exception->getMessage());
                $rejected++;
            }
        }

        $tank->syncState($state);

        return new SettlementReport($applied, $rejected);
    }

    private function apply(TankState $state, CargoMovement $movement): TankState
    {
        $net = new Litres($movement->net_litres);

        return match ($movement->direction) {
            MovementDirection::Inbound => $state->receive($movement->commodity, $net),
            MovementDirection::Outbound => $state->dispatch($movement->commodity, $net),
        };
    }
}
