<?php

declare(strict_types=1);

/**
 * Child process for ConcurrentSettlementTest. Ingests logs one at a time, each
 * ingest settling the tank, so that workers overlap as much as possible. Any
 * failure exits non-zero so the test reports the cause.
 */

use App\Application\Cargo\Actions\IngestMovementLogs;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $worker, $logCount] = $argv;

$ingest = $app->make(IngestMovementLogs::class);

try {
    for ($i = 0; $i < (int) $logCount; $i++) {
        $ingest->handle([[
            'reference' => "race-{$worker}-{$i}",
            'direction' => 'inbound',
            'commodity' => 'CPO',
            'tank_code' => 'T-RACE',
            'counterparty' => "MV Worker {$worker}",
            'litres' => 1_000,
            'occurred_at' => '2026-11-01T08:00:00Z',
        ]]);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());

    exit(1);
}
