<?php

namespace App\Filament\Resources\StorageTanks\Schemas;

use App\Domain\Cargo\Enums\Commodity;
use App\Filament\Support\CargoPresentation;
use App\Models\StorageTank;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StorageTankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('commodity')
                    ->options(CargoPresentation::commodityOptions())
                    ->required()
                    ->native(false)
                    ->live()
                    ->disabled(fn (?StorageTank $record): bool => ($record?->current_volume_litres ?? 0) > 0)
                    ->helperText(fn (Get $get, ?StorageTank $record): ?string => self::commodityHelp($get, $record)),
                TextInput::make('capacity_litres')
                    ->label('Capacity')
                    ->required()
                    ->integer()
                    ->suffix('L')
                    ->minValue(fn (?StorageTank $record): int => max(1, $record?->current_volume_litres ?? 1))
                    ->helperText(fn (?StorageTank $record): ?string => $record === null
                        ? null
                        : 'Cannot go below the current balance of '.CargoPresentation::litres($record->current_volume_litres).'.'),
            ]);
    }

    private static function commodityHelp(Get $get, ?StorageTank $record): ?string
    {
        if (($record?->current_volume_litres ?? 0) > 0) {
            return 'The tank must be empty before its commodity can change.';
        }

        $commodity = $get('commodity');

        return ($commodity instanceof Commodity ? $commodity : Commodity::tryFrom((string) $commodity))?->handlingNotes();
    }
}
