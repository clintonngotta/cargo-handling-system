<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Enums\Commodity;
use App\Filament\Support\CargoPresentation;
use App\Models\StorageTank;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TankLevels extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tank levels')
            ->query(StorageTank::query()->orderBy('code'))
            ->poll('5s')
            ->paginated(false)
            ->columns([
                TextColumn::make('code')->label('Tank'),
                TextColumn::make('commodity')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Commodity $state): string => $state->value),
                TextColumn::make('current_volume_litres')
                    ->label('Balance')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd(),
                TextColumn::make('capacity_litres')
                    ->label('Capacity')
                    ->formatStateUsing(fn (int $state): string => CargoPresentation::litres($state))
                    ->alignEnd(),
                CargoPresentation::tankFillColumn(),
            ]);
    }
}
