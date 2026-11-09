<?php

namespace App\Filament\Resources\CargoMovements;

use App\Filament\Resources\CargoMovements\Pages\CreateCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\ListCargoMovements;
use App\Filament\Resources\CargoMovements\Schemas\CargoMovementForm;
use App\Filament\Resources\CargoMovements\Tables\CargoMovementsTable;
use App\Models\CargoMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CargoMovementResource extends Resource
{
    protected static ?string $model = CargoMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'external_reference';

    public static function form(Schema $schema): Schema
    {
        return CargoMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CargoMovementsTable::configure($table);
    }

    /**
     * The ledger is an audit trail. Movements only change through settlement or a retry.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCargoMovements::route('/'),
            'create' => CreateCargoMovement::route('/create'),
        ];
    }
}
