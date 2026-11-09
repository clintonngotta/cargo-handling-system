<?php

declare(strict_types=1);

namespace Tests\Feature\Cargo;

use App\Domain\Cargo\Enums\MovementStatus;
use App\Models\CargoMovement;
use App\Models\StorageTank;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Races real OS processes, so it needs a database with row locks. It is skipped
 * on SQLite. See "Testing" in the README for how to run it against MySQL.
 */
class ConcurrentSettlementTest extends TestCase
{
    use DatabaseMigrations;

    private const int WORKERS = 8;

    private const int LOGS_PER_WORKER = 10;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Requires a database with row locks, such as MySQL.');
        }
    }

    public function test_racing_workers_never_overfill_a_tank_or_lose_an_update(): void
    {
        // Room for only 30 of the 80 competing 1,000 L inbound logs (995 L net each).
        $tank = StorageTank::factory()->create(['code' => 'T-RACE', 'capacity_litres' => 29_850]);

        $this->runWorkers();

        $applied = CargoMovement::query()->where('status', MovementStatus::Applied)->count();
        $rejected = CargoMovement::query()->where('status', MovementStatus::Rejected)->count();

        $this->assertSame(self::WORKERS * self::LOGS_PER_WORKER, $applied + $rejected);
        $this->assertSame(30, $applied);
        $this->assertSame(50, $rejected);
        $this->assertSame(29_850, $tank->fresh()->current_volume_litres);
    }

    private function runWorkers(): void
    {
        $processes = [];
        $pipes = [];

        for ($worker = 0; $worker < self::WORKERS; $worker++) {
            $processes[$worker] = proc_open(
                [PHP_BINARY, base_path('tests/Support/settlement-worker.php'), (string) $worker, (string) self::LOGS_PER_WORKER],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$worker],
            );
        }

        foreach ($processes as $worker => $process) {
            $output = stream_get_contents($pipes[$worker][1]).stream_get_contents($pipes[$worker][2]);

            $this->assertSame(0, proc_close($process), "Worker {$worker} failed: {$output}");
        }
    }
}
