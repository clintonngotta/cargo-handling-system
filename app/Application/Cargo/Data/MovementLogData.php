<?php

declare(strict_types=1);

namespace App\Application\Cargo\Data;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Exceptions\InvalidMovementLogException;
use App\Domain\Cargo\ValueObjects\Litres;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A validated source log. Timestamps are normalised to UTC here so logs from
 * different time zones sort correctly.
 */
final readonly class MovementLogData
{
    public function __construct(
        public string $reference,
        public MovementDirection $direction,
        public Commodity $commodity,
        public string $tankCode,
        public string $counterparty,
        public Litres $grossQuantity,
        public CarbonImmutable $occurredAt,
    ) {}

    /** @param array<string, mixed> $log */
    public static function fromArray(array $log): self
    {
        return new self(
            reference: self::requiredString($log, 'reference'),
            direction: MovementDirection::tryFrom(self::requiredString($log, 'direction'))
                ?? throw InvalidMovementLogException::invalidField('direction', $log['direction']),
            commodity: Commodity::tryFrom(self::requiredString($log, 'commodity'))
                ?? throw InvalidMovementLogException::invalidField('commodity', $log['commodity']),
            tankCode: self::requiredString($log, 'tank_code'),
            counterparty: self::requiredString($log, 'counterparty'),
            grossQuantity: self::positiveLitres($log),
            occurredAt: self::timestamp($log),
        );
    }

    /** @param array<string, mixed> $log */
    private static function requiredString(array $log, string $field): string
    {
        $value = $log[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw InvalidMovementLogException::invalidField($field, $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $log */
    private static function positiveLitres(array $log): Litres
    {
        $value = $log['litres'] ?? null;

        if (! is_int($value) || $value <= 0) {
            throw InvalidMovementLogException::invalidField('litres', $value);
        }

        return new Litres($value);
    }

    /** @param array<string, mixed> $log */
    private static function timestamp(array $log): CarbonImmutable
    {
        $value = self::requiredString($log, 'occurred_at');

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            throw InvalidMovementLogException::invalidField('occurred_at', $value);
        }
    }
}
