<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Enums;

enum MovementDirection: string
{
    /** Vessel discharge into a shore tank. */
    case Inbound = 'inbound';

    /** Truck delivery out of a shore tank. */
    case Outbound = 'outbound';
}
