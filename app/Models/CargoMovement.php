<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Observers\CargoMovementObserver;
use Carbon\CarbonImmutable;
use Database\Factories\CargoMovementFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $external_reference
 * @property MovementDirection $direction
 * @property Commodity $commodity
 * @property int $storage_tank_id
 * @property string $counterparty
 * @property int $gross_litres
 * @property int $transit_loss_litres
 * @property int $net_litres
 * @property CarbonImmutable $occurred_at
 * @property MovementStatus $status
 * @property string|null $rejection_reason
 * @property int|null $balance_after_litres
 * @property CarbonImmutable|null $settled_at
 */
#[ObservedBy(CargoMovementObserver::class)]
class CargoMovement extends Model
{
    /** @use HasFactory<CargoMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'external_reference',
        'direction',
        'commodity',
        'storage_tank_id',
        'counterparty',
        'gross_litres',
        'occurred_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => MovementDirection::class,
            'commodity' => Commodity::class,
            'status' => MovementStatus::class,
            'gross_litres' => 'integer',
            'transit_loss_litres' => 'integer',
            'net_litres' => 'integer',
            'balance_after_litres' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<StorageTank, $this> */
    public function tank(): BelongsTo
    {
        return $this->belongsTo(StorageTank::class, 'storage_tank_id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', MovementStatus::Pending);
    }

    /**
     * The id tie-break keeps ordering deterministic for equal timestamps.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderBy('occurred_at')->orderBy('id');
    }

    public function markApplied(Litres $balanceAfter): void
    {
        $this->status = MovementStatus::Applied;
        $this->balance_after_litres = $balanceAfter->value;
        $this->settled_at = CarbonImmutable::now();
        $this->save();
    }

    public function markRejected(string $reason): void
    {
        $this->status = MovementStatus::Rejected;
        $this->rejection_reason = $reason;
        $this->settled_at = CarbonImmutable::now();
        $this->save();
    }

    public function requeue(): void
    {
        $this->status = MovementStatus::Pending;
        $this->rejection_reason = null;
        $this->settled_at = null;
        $this->save();
    }
}
