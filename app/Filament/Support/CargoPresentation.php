<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Models\StorageTank;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

/**
 * Display concerns for domain types. They live here because the Domain layer
 * cannot implement Filament's label and colour contracts.
 */
final class CargoPresentation
{
    private const float NEARLY_FULL_PERCENT = 85.0;

    private const float CRITICALLY_FULL_PERCENT = 95.0;

    /** @return array<string, string> */
    public static function commodityOptions(): array
    {
        $options = [];

        foreach (Commodity::cases() as $commodity) {
            $options[$commodity->value] = "{$commodity->value}: {$commodity->label()}";
        }

        return $options;
    }

    public static function litres(?int $litres): string
    {
        return $litres === null ? '-' : number_format($litres).' L';
    }

    public static function directionLabel(MovementDirection $direction): string
    {
        return match ($direction) {
            MovementDirection::Inbound => 'Vessel discharge',
            MovementDirection::Outbound => 'Truck delivery',
        };
    }

    public static function directionIcon(MovementDirection $direction): Heroicon
    {
        return match ($direction) {
            MovementDirection::Inbound => Heroicon::OutlinedArrowDownTray,
            MovementDirection::Outbound => Heroicon::OutlinedArrowUpTray,
        };
    }

    public static function statusLabel(MovementStatus $status): string
    {
        return ucfirst($status->value);
    }

    public static function statusColor(MovementStatus $status): string
    {
        return match ($status) {
            MovementStatus::Pending => 'gray',
            MovementStatus::Applied => 'success',
            MovementStatus::Rejected => 'danger',
        };
    }

    public static function statusIcon(MovementStatus $status): Heroicon
    {
        return match ($status) {
            MovementStatus::Pending => Heroicon::OutlinedClock,
            MovementStatus::Applied => Heroicon::OutlinedCheckCircle,
            MovementStatus::Rejected => Heroicon::OutlinedXCircle,
        };
    }

    public static function tankFillColumn(): TextColumn
    {
        return TextColumn::make('fill')
            ->label('Full')
            ->state(fn (StorageTank $record): float => self::fillPercent($record))
            ->formatStateUsing(fn (float $state): string => number_format($state, 1).'%')
            ->badge()
            ->color(fn (float $state): string => match (true) {
                $state >= self::CRITICALLY_FULL_PERCENT => 'danger',
                $state >= self::NEARLY_FULL_PERCENT => 'warning',
                default => 'gray',
            })
            ->icon(fn (float $state): ?Heroicon => $state >= self::NEARLY_FULL_PERCENT ? Heroicon::OutlinedExclamationTriangle : null)
            ->alignEnd();
    }

    private static function fillPercent(StorageTank $tank): float
    {
        return $tank->capacity_litres === 0 ? 0.0 : $tank->current_volume_litres / $tank->capacity_litres * 100;
    }
}
