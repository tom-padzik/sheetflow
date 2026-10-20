<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Tpadzik\Sheetflow\Enums\RunStatus;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Services\ImportPipeline;

class StageImportRows implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $runId,
    ) {}

    public function handle(ImportPipeline $pipeline): void
    {
        $pipeline->stage($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        ImportRun::query()
            ->whereKey($this->runId)
            ->where('status', '!=', RunStatus::cancelled->value)
            ->update([
                'status' => RunStatus::failed->value,
            ]);
    }
}
