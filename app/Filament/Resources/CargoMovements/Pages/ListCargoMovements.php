<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Filament\Resources\CargoMovements\CargoMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCargoMovements extends ListRecords
{
    protected static string $resource = CargoMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Log movement'),
        ];
    }
}
