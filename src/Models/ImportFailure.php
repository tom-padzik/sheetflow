<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportFailure extends Model
{
    protected $table = 'sheetflow_failures';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'run_id',
        'row_number',
        'values',
        'errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'errors' => 'array',
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
