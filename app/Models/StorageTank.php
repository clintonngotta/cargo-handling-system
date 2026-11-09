<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Domain\Cargo\ValueObjects\TankState;
use App\Observers\StorageTankObserver;
use Database\Factories\StorageTankFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property Commodity $commodity
 * @property int $capacity_litres
 * @property int $current_volume_litres
 */
#[ObservedBy(StorageTankObserver::class)]
class StorageTank extends Model
{
    /** @use HasFactory<StorageTankFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'commodity',
        'capacity_litres',
    ];

    protected $attributes = [
        'current_volume_litres' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'commodity' => Commodity::class,
            'capacity_litres' => 'integer',
            'current_volume_litres' => 'integer',
        ];
    }

    /** @return HasMany<CargoMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }

    public function state(): TankState
    {
        return new TankState(
            $this->code,
            $this->commodity,
            new Litres($this->capacity_litres),
            new Litres($this->current_volume_litres),
        );
    }

    public function syncState(TankState $state): void
    {
        $this->current_volume_litres = $state->volume->value;
        $this->save();
    }
}
