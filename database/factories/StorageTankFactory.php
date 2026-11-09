<?php

namespace Database\Factories;

use App\Domain\Cargo\Enums\Commodity;
use App\Models\StorageTank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorageTank> */
class StorageTankFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'T-'.fake()->unique()->numerify('####'),
            'name' => 'Shore Tank '.fake()->unique()->numerify('####'),
            'commodity' => Commodity::CrudePalmOil,
            'capacity_litres' => 1_000_000,
        ];
    }

    public function holding(int $litres): static
    {
        return $this->afterCreating(function (StorageTank $tank) use ($litres): void {
            $tank->current_volume_litres = $litres;
            $tank->save();
        });
    }
}
