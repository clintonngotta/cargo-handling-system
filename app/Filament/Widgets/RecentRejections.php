<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Filament\Actions\RetryMovementAction;
use App\Models\CargoMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentRejections extends TableWidget
{
    private const int LIMIT = 5;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent rejections')
            ->query(CargoMovement::query()->with('tank')->where('status', MovementStatus::Rejected)->latest('settled_at'))
            ->poll('5s')
            ->paginated([self::LIMIT])
            ->emptyStateHeading('No rejected movements')
            ->columns([
                TextColumn::make('external_reference')->label('Reference'),
                TextColumn::make('tank.code')->label('Tank'),
                TextColumn::make('occurred_at')->label('Occurred (UTC)')->dateTime('d M Y H:i'),
                TextColumn::make('rejection_reason')->label('Reason')->wrap(),
            ])
            ->recordActions([
                RetryMovementAction::make(),
            ]);
    }
}
