<?php

declare(strict_types=1);

namespace App\Application\Cargo\Actions;

use App\Application\Cargo\Data\MovementPreview;
use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Exceptions\CargoDomainException;
use App\Domain\Cargo\Services\TransitLossPolicy;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Models\StorageTank;

/**
 * Dry-runs a movement against a tank's current state without writing anything,
 * using the same domain rules as settlement.
 */
final readonly class PreviewMovement
{
    public function __construct(private TransitLossPolicy $lossPolicy) {}

    public function handle(MovementDirection $direction, Commodity $commodity, StorageTank $tank, Litres $gross): MovementPreview
    {
        $quantity = $this->lossPolicy->assess($direction, $gross);

        try {
            $state = match ($direction) {
                MovementDirection::Inbound => $tank->state()->receive($commodity, $quantity->net),
                MovementDirection::Outbound => $tank->state()->dispatch($commodity, $quantity->net),
            };
        } catch (CargoDomainException $exception) {
            return new MovementPreview($quantity, null, $exception->getMessage());
        }

        return new MovementPreview($quantity, $state->volume, null);
    }
}
