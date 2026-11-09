<?php

namespace App\Filament\Resources\StorageTanks\Pages;

use App\Filament\Resources\StorageTanks\StorageTankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStorageTanks extends ListRecords
{
    protected static string $resource = StorageTankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
