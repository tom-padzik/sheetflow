<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tpadzik\Sheetflow\Services\ImportPipeline;

class ImportRowsChunk implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    /**
     * @param  list<int>  $rowIds
     */
    public function __construct(
        public readonly string $runId,
        public readonly array $rowIds,
    ) {}

    public function handle(ImportPipeline $pipeline): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $pipeline->importChunk(
            runId: $this->runId,
            rowIds: $this->rowIds,
        );
    }
}
