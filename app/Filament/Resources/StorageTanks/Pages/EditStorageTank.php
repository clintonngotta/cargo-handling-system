<?php

namespace App\Filament\Resources\StorageTanks\Pages;

use App\Application\Cargo\Actions\UpdateStorageTank;
use App\Domain\Cargo\Exceptions\CargoDomainException;
use App\Filament\Resources\StorageTanks\StorageTankResource;
use App\Models\StorageTank;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStorageTank extends EditRecord
{
    protected static string $resource = StorageTankResource::class;

    /**
     * The balance can change while the form is open, so a save that passed form
     * validation can still be refused.
     *
     * @param  StorageTank  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateStorageTank::class)->handle($record, $data);
        } catch (CargoDomainException $exception) {
            Notification::make()->title('Tank not saved')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
