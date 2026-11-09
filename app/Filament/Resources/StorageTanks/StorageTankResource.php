<?php

namespace App\Filament\Resources\StorageTanks;

use App\Filament\Resources\StorageTanks\Pages\CreateStorageTank;
use App\Filament\Resources\StorageTanks\Pages\EditStorageTank;
use App\Filament\Resources\StorageTanks\Pages\ListStorageTanks;
use App\Filament\Resources\StorageTanks\Schemas\StorageTankForm;
use App\Filament\Resources\StorageTanks\Tables\StorageTanksTable;
use App\Models\StorageTank;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StorageTankResource extends Resource
{
    protected static ?string $model = StorageTank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return StorageTankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorageTanksTable::configure($table);
    }

    /**
     * Tanks are referenced by the movement audit trail, so they are never deleted.
     */
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
            'index' => ListStorageTanks::route('/'),
            'create' => CreateStorageTank::route('/create'),
            'edit' => EditStorageTank::route('/{record}/edit'),
        ];
    }
}
