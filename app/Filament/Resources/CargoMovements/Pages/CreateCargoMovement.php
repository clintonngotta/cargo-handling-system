<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Application\Cargo\Actions\IngestMovementLogs;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Domain\Cargo\Exceptions\CargoDomainException;
use App\Filament\Resources\CargoMovements\CargoMovementResource;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateCargoMovement extends CreateRecord
{
    protected static string $resource = CargoMovementResource::class;

    protected static ?string $title = 'Log movement';

    protected static bool $canCreateAnother = false;

    /**
     * Sends the form through the same pipeline as any other log source.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            app(IngestMovementLogs::class)->handle([[
                'reference' => $data['external_reference'],
                'direction' => $data['direction'],
                'commodity' => $data['commodity'],
                'tank_code' => StorageTank::query()->findOrFail($data['storage_tank_id'])->code,
                'counterparty' => $data['counterparty'],
                'litres' => (int) $data['gross_litres'],
                'occurred_at' => $data['occurred_at'],
            ]]);
        } catch (CargoDomainException $exception) {
            Notification::make()->title('Movement not recorded')->body($exception->getMessage())->danger()->send();

            throw new Halt;
        }

        return CargoMovement::query()->where('external_reference', $data['external_reference'])->firstOrFail();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Log movement');
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var CargoMovement $movement */
        $movement = $this->getRecord();

        if ($movement->status === MovementStatus::Rejected) {
            return Notification::make()
                ->title("{$movement->external_reference} was rejected")
                ->body($movement->rejection_reason)
                ->danger();
        }

        return Notification::make()->title("{$movement->external_reference} applied")->success();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
