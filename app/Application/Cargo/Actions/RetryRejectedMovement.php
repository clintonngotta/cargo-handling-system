<?php

declare(strict_types=1);

namespace App\Application\Cargo\Actions;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\ImmutableMovementException;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Support\Facades\DB;

/**
 * Returns a rejected movement to pending and settles its tank again. The status
 * is re-read under the tank lock, so two operators retrying at once cannot apply
 * the movement twice.
 */
final readonly class RetryRejectedMovement
{
    public function __construct(private SettlePendingMovements $settle) {}

    public function handle(CargoMovement $movement): CargoMovement
    {
        DB::transaction(function () use ($movement): void {
            StorageTank::query()->lockForUpdate()->findOrFail($movement->storage_tank_id);

            $current = CargoMovement::query()->findOrFail($movement->id);

            if ($current->status !== MovementStatus::Rejected) {
                throw ImmutableMovementException::notRejected($current->external_reference);
            }

            $current->requeue();
        });

        $this->settle->handle([$movement->storage_tank_id]);

        return CargoMovement::query()->findOrFail($movement->id);
    }
}
