<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Enums;

enum MovementStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';
}
