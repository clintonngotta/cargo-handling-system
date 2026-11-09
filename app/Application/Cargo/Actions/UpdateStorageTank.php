<?php

declare(strict_types=1);

namespace App\Application\Cargo\Actions;

use App\Models\StorageTank;
use Illuminate\Support\Facades\DB;

/**
 * Saves tank details under the same row lock as settlement. The entity hooks
 * then check the change against the live balance, not the copy loaded when an
 * edit form was opened.
 */
final class UpdateStorageTank
{
    /** @param array<string, mixed> $attributes */
    public function handle(StorageTank $tank, array $attributes): StorageTank
    {
        return DB::transaction(function () use ($tank, $attributes): StorageTank {
            $current = StorageTank::query()->lockForUpdate()->findOrFail($tank->id);
            $current->update($attributes);

            return $current;
        });
    }
}
