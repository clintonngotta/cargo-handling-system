<?php

namespace Database\Factories;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CargoMovement> */
class CargoMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'external_reference' => fake()->unique()->uuid(),
            'direction' => MovementDirection::Inbound,
            'commodity' => Commodity::CrudePalmOil,
            'storage_tank_id' => StorageTank::factory(),
            'counterparty' => 'MV '.fake()->lastName(),
            'gross_litres' => 100_000,
            'occurred_at' => now(),
        ];
    }
}
