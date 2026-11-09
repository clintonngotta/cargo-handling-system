<?php

namespace App\Filament\Resources\CargoMovements\Schemas;

use App\Application\Cargo\Actions\PreviewMovement;
use App\Application\Cargo\Data\MovementPreview;
use App\Domain\Cargo\Enums\Commodity;
use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\ValueObjects\Litres;
use App\Filament\Support\CargoPresentation;
use App\Models\StorageTank;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The reactive movement form. Business rules stay in PreviewMovement, so the
 * figures shown here always match what settlement will do.
 */
class CargoMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('direction')
                            ->options(self::directionOptions())
                            ->icons([
                                MovementDirection::Inbound->value => CargoPresentation::directionIcon(MovementDirection::Inbound),
                                MovementDirection::Outbound->value => CargoPresentation::directionIcon(MovementDirection::Outbound),
                            ])
                            ->inline()
                            ->default(MovementDirection::Inbound->value)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('commodity')
                            ->options(CargoPresentation::commodityOptions())
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('storage_tank_id', null))
                            ->helperText(fn (Get $get): ?string => self::commodity($get)?->handlingNotes()),
                        Select::make('storage_tank_id')
                            ->label('Tank')
                            ->options(fn (Get $get): array => self::tankOptions($get))
                            ->native(false)
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get): bool => self::commodity($get) === null)
                            ->helperText(fn (Get $get): ?string => self::commodity($get) === null ? 'Choose a commodity first.' : null),
                        TextInput::make('counterparty')
                            ->label(fn (Get $get): string => self::direction($get) === MovementDirection::Outbound ? 'Truck registration' : 'Vessel name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('gross_litres')
                            ->label(fn (Get $get): string => self::direction($get) === MovementDirection::Outbound ? 'Quantity loaded' : 'Quantity discharged')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->suffix('L')
                            ->live(debounce: 400),
                        DateTimePicker::make('occurred_at')
                            ->label('Occurred at (UTC)')
                            ->seconds(false)
                            ->default(fn (): string => now()->format('Y-m-d H:i'))
                            ->required(),
                        TextInput::make('external_reference')
                            ->label('Reference')
                            ->required()
                            ->maxLength(255)
                            ->unique()
                            ->default(fn (): string => 'MAN-'.Str::upper(Str::random(10))),
                    ]),
                Section::make('Settlement preview')
                    ->description('Based on the tank\'s current balance. Nothing is saved until you submit.')
                    ->columns(3)
                    ->visible(fn (Get $get): bool => self::preview($get) !== null)
                    ->schema([
                        TextEntry::make('preview_loss')
                            ->label('Transit loss (0.5%)')
                            ->state(fn (Get $get): string => CargoPresentation::litres(self::preview($get)?->quantity->loss->value))
                            ->visible(fn (Get $get): bool => self::direction($get) === MovementDirection::Inbound),
                        TextEntry::make('preview_net')
                            ->label(fn (Get $get): string => self::direction($get) === MovementDirection::Outbound ? 'Leaves the tank' : 'Reaches the tank')
                            ->state(fn (Get $get): string => CargoPresentation::litres(self::preview($get)?->quantity->net->value)),
                        TextEntry::make('preview_balance')
                            ->label('Balance after')
                            ->state(fn (Get $get): string => CargoPresentation::litres(self::preview($get)?->balanceAfter?->value)),
                        TextEntry::make('preview_problem')
                            ->label('This movement would be rejected')
                            ->state(fn (Get $get): ?string => self::preview($get)?->problem)
                            ->visible(fn (Get $get): bool => self::preview($get)?->problem !== null)
                            ->color('danger')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function directionOptions(): array
    {
        $options = [];

        foreach (MovementDirection::cases() as $direction) {
            $options[$direction->value] = CargoPresentation::directionLabel($direction);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function tankOptions(Get $get): array
    {
        $commodity = self::commodity($get);

        if ($commodity === null) {
            return [];
        }

        return StorageTank::query()
            ->where('commodity', $commodity)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (StorageTank $tank): array => [
                $tank->id => sprintf(
                    '%s (%s of %s)',
                    $tank->code,
                    CargoPresentation::litres($tank->current_volume_litres),
                    CargoPresentation::litres($tank->capacity_litres),
                ),
            ])
            ->all();
    }

    private static function preview(Get $get): ?MovementPreview
    {
        $direction = self::direction($get);
        $commodity = self::commodity($get);
        $gross = filter_var($get('gross_litres'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tank = StorageTank::query()->find($get('storage_tank_id'));

        if ($direction === null || $commodity === null || $gross === false || $tank === null) {
            return null;
        }

        return app(PreviewMovement::class)->handle($direction, $commodity, $tank, new Litres($gross));
    }

    private static function direction(Get $get): ?MovementDirection
    {
        return MovementDirection::tryFrom((string) $get('direction'));
    }

    private static function commodity(Get $get): ?Commodity
    {
        return Commodity::tryFrom((string) $get('commodity'));
    }
}
