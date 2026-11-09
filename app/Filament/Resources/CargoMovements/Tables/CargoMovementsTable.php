<?php

namespace App\Filament\Resources\CargoMovements\Tables;

use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Filament\Actions\RetryMovementAction;
use App\Filament\Support\CargoPresentation;
use App\Models\CargoMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CargoMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Occurred (UTC)')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('external_reference')
                    ->label('Reference')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MovementStatus $state): string => CargoPresentation::statusLabel($state))
                    ->color(fn (MovementStatus $state): string => CargoPresentation::statusColor($state))
                    ->icon(fn (MovementStatus $state) => CargoPresentation::statusIcon($state))
                    ->tooltip(fn (CargoMovement $record): ?string => $record->rejection_reason),
                TextColumn::make('rejection_reason')
                    ->label('Reason')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('direction')
                    ->formatStateUsing(fn (MovementDirection $state): string => $state === MovementDirection::Inbound ? 'Vessel' : 'Truck')
                    ->icon(fn (MovementDirection $state) => CargoPresentation::directionIcon($state)),
                TextColumn::make('commodity')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Commodity $state): string => $state->value)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tank.code')
                    ->label('Tank'),
                TextColumn::make('counterparty')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gross_litres')
                    ->label('Gross')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd(),
                TextColumn::make('transit_loss_litres')
                    ->label('Loss')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd(),
                TextColumn::make('net_litres')
                    ->label('Net')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd(),
                TextColumn::make('balance_after_litres')
                    ->label('Balance after')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->placeholder('-')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::optionsFor(MovementStatus::cases(), CargoPresentation::statusLabel(...))),
                SelectFilter::make('direction')->options(self::optionsFor(MovementDirection::cases(), CargoPresentation::directionLabel(...))),
                SelectFilter::make('commodity')->options(CargoPresentation::commodityOptions()),
                SelectFilter::make('storage_tank_id')->label('Tank')->relationship('tank', 'code'),
            ])
            ->recordActions([
                RetryMovementAction::make(),
            ]);
    }

    /**
     * @param  list<MovementStatus|MovementDirection>  $cases
     * @return array<string, string>
     */
    private static function optionsFor(array $cases, callable $label): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $label($case);
        }

        return $options;
    }
}
