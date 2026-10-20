<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tpadzik\Sheetflow\Enums\RowStatus;

class ImportRow extends Model
{
    protected $table = 'sheetflow_rows';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'run_id',
        'row_number',
        'values',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'status' => RowStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'run_id');
    }
}
