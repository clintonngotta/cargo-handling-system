<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Exceptions;

use DomainException;

/** Base type for every business-rule violation in the cargo domain. */
abstract class CargoDomainException extends DomainException {}
