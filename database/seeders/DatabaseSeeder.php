<?php

namespace Database\Seeders;

use App\Application\Cargo\Actions\IngestMovementLogs;
use App\Domain\Cargo\Enums\Commodity;
use App\Models\StorageTank;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data. Model events stay enabled on purpose: the observers derive the
 * transit loss and guard tank capacity.
 */
class DatabaseSeeder extends Seeder
{
    public function run(IngestMovementLogs $ingestMovementLogs): void
    {
        User::factory()->create([
            'name' => 'Terminal Operator',
            'email' => 'operator@bulkstream.test',
            'password' => 'password',
        ]);

        StorageTank::factory()->create([
            'code' => 'CPO-01',
            'name' => 'Crude Palm Oil Tank 1',
            'commodity' => Commodity::CrudePalmOil,
            'capacity_litres' => 5_000_000,
        ]);

        StorageTank::factory()->create([
            'code' => 'CPOL-01',
            'name' => 'Crude Palm Olein Tank 1',
            'commodity' => Commodity::CrudePalmOlein,
            'capacity_litres' => 3_000_000,
        ]);

        StorageTank::factory()->create([
            'code' => 'PFAD-01',
            'name' => 'Palm Fatty Acid Distillate Tank 1',
            'commodity' => Commodity::PalmFattyAcidDistillate,
            'capacity_litres' => 1_000_000,
        ]);

        $ingestMovementLogs->handle([
            $this->log('TRK-1001', 'outbound', 'CPO', 'CPO-01', 'KCA 123A', 40_000, '2026-11-02T10:00:00+03:00'),
            $this->log('VSL-2001', 'inbound', 'CPO', 'CPO-01', 'MV Example Star', 2_000_000, '2026-11-02T06:00:00+03:00'),
            $this->log('VSL-2002', 'inbound', 'CPOL', 'CPOL-01', 'MV Example Dawn', 1_500_000, '2026-11-02T07:30:00+03:00'),
            $this->log('TRK-1002', 'outbound', 'CPOL', 'CPOL-01', 'KDB 456B', 30_000, '2026-11-02T11:15:00+03:00'),
            $this->log('VSL-2003', 'inbound', 'PFAD', 'PFAD-01', 'MV Example Tide', 1_200_000, '2026-11-02T08:00:00+03:00'),
        ]);
    }

    /**
     * @return array{reference: string, direction: string, commodity: string, tank_code: string, counterparty: string, litres: int, occurred_at: string}
     */
    private function log(
        string $reference,
        string $direction,
        string $commodity,
        string $tankCode,
        string $counterparty,
        int $litres,
        string $occurredAt,
    ): array {
        return [
            'reference' => $reference,
            'direction' => $direction,
            'commodity' => $commodity,
            'tank_code' => $tankCode,
            'counterparty' => $counterparty,
            'litres' => $litres,
            'occurred_at' => $occurredAt,
        ];
    }
}
