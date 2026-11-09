<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Application\Cargo\Actions\RetryRejectedMovement;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\CargoDomainException;
use App\Models\CargoMovement;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class RetryMovementAction
{
    public static function make(): Action
    {
        return Action::make('retry')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (CargoMovement $record): bool => $record->status === MovementStatus::Rejected)
            ->requiresConfirmation()
            ->modalHeading(fn (CargoMovement $record): string => "Retry {$record->external_reference}?")
            ->modalDescription(fn (CargoMovement $record): string => "Rejected because: {$record->rejection_reason} The movement is checked again against the tank's current state.")
            ->action(function (CargoMovement $record, RetryRejectedMovement $retry): void {
                try {
                    self::notifyOutcome($retry->handle($record));
                } catch (CargoDomainException $exception) {
                    Notification::make()->title('Retry failed')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    private static function notifyOutcome(CargoMovement $movement): void
    {
        if ($movement->status === MovementStatus::Applied) {
            Notification::make()->title("{$movement->external_reference} applied")->success()->send();

            return;
        }

        Notification::make()
            ->title("{$movement->external_reference} is still rejected")
            ->body($movement->rejection_reason)
            ->danger()
            ->send();
    }
}
