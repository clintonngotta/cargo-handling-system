<?php

namespace App\Filament\Resources\StorageTanks\Tables;

use App\Domain\Cargo\Enums\Commodity;
use App\Filament\Support\CargoPresentation;
use App\Models\StorageTank;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StorageTanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('commodity')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Commodity $state): string => $state->value),
                TextColumn::make('current_volume_litres')
                    ->label('Balance')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('capacity_litres')
                    ->label('Capacity')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('available')
                    ->state(fn (StorageTank $record): string => CargoPresentation::litres($record->state()->available()->value))
                    ->alignEnd(),
                CargoPresentation::tankFillColumn(),
            ])
            ->filters([
                SelectFilter::make('commodity')->options(CargoPresentation::commodityOptions()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
