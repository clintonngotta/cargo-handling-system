<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Cargo\Exceptions\CommodityMismatchException;
use App\Models\StorageTank;

/**
 * Entity state hooks. They guard every write path to a tank, including admin
 * edits that never pass through the settlement action.
 */
class StorageTankObserver
{
    public function saving(StorageTank $tank): void
    {
        // Building the state throws if the volume or a reduced capacity breaks the limit.
        $tank->state();
    }

    public function updating(StorageTank $tank): void
    {
        if ($tank->isDirty('commodity') && $tank->getOriginal('current_volume_litres') > 0) {
            throw CommodityMismatchException::tankNotEmpty($tank->code);
        }
    }
}
