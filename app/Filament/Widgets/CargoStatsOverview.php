<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Enums\MovementDirection;
use App\Domain\Cargo\Enums\MovementStatus;
use App\Filament\Support\CargoPresentation;
use App\Models\CargoMovement;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CargoStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '5s';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $applied = CargoMovement::query()->where('status', MovementStatus::Applied);
        $rejected = CargoMovement::query()->where('status', MovementStatus::Rejected)->count();

        return [
            Stat::make('Received', CargoPresentation::litres((int) (clone $applied)->where('direction', MovementDirection::Inbound)->sum('net_litres')))
                ->description('Net of transit loss')
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray),
            Stat::make('Delivered', CargoPresentation::litres((int) (clone $applied)->where('direction', MovementDirection::Outbound)->sum('net_litres')))
                ->description('By truck')
                ->descriptionIcon(Heroicon::OutlinedArrowUpTray),
            Stat::make('Transit loss', CargoPresentation::litres((int) (clone $applied)->sum('transit_loss_litres')))
                ->description('0.5% of inbound flows')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingDown),
            Stat::make('Rejected movements', number_format($rejected))
                ->description($rejected === 0 ? 'Nothing to review' : 'Needs review')
                ->descriptionIcon($rejected === 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                ->color($rejected === 0 ? 'gray' : 'danger'),
        ];
    }
}
