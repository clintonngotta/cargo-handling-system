<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\ImmutableMovementException;
use App\Domain\Cargo\Services\TransitLossPolicy;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Models\CargoMovement;

/**
 * Entity state hooks. Every movement gets its transit loss from the policy,
 * however it is created. Settled movements stay a permanent audit record, with
 * one exception: a rejected movement may return to pending for a retry.
 */
class CargoMovementObserver
{
    private const array REQUEUE_ATTRIBUTES = ['status', 'rejection_reason', 'settled_at'];

    public function __construct(private readonly TransitLossPolicy $lossPolicy) {}

    public function saving(CargoMovement $movement): void
    {
        if ($movement->exists && $movement->getOriginal('status') !== MovementStatus::Pending && ! $this->isRequeue($movement)) {
            throw ImmutableMovementException::alreadySettled($movement->external_reference);
        }

        $quantity = $this->lossPolicy->assess($movement->direction, new Litres($movement->gross_litres));

        $movement->transit_loss_litres = $quantity->loss->value;
        $movement->net_litres = $quantity->net->value;
    }

    public function deleting(CargoMovement $movement): void
    {
        if ($movement->status !== MovementStatus::Pending) {
            throw ImmutableMovementException::alreadySettled($movement->external_reference);
        }
    }

    private function isRequeue(CargoMovement $movement): bool
    {
        return $movement->getOriginal('status') === MovementStatus::Rejected
            && $movement->status === MovementStatus::Pending
            && array_diff(array_keys($movement->getDirty()), self::REQUEUE_ATTRIBUTES) === [];
    }
}
