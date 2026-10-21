<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tpadzik\Sheetflow\Enums\RunStatus;

class ImportRun extends Model
{
    use HasUuids;

    protected $table = 'sheetflow_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'definition_key',
        'status',
        'disk',
        'path',
        'original_filename',
        'format',
        'delimiter',
        'sheet',
        'header_signature',
        'headers',
        'sheet_names',
        'preview_rows',
        'column_map',
        'invalid_actions',
        'file_errors',
        'total_rows',
        'processed_rows',
        'valid_rows',
        'failed_rows',
        'stopped_row',
        'imported_rows',
        'user_id',
        'batch_id',
        'staged_at',
        'validated_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'user_id' => 'integer',
            'headers' => 'array',
            'sheet_names' => 'array',
            'preview_rows' => 'array',
            'column_map' => 'array',
            'invalid_actions' => 'array',
            'file_errors' => 'array',
            'stopped_row' => 'integer',
            'staged_at' => 'datetime',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function ownedBy(?Authenticatable $user): bool
    {
        if (null === $user) {
            return null === $this->user_id;
        }

        $ownerId = $this->normalizeOwnerId($user->getAuthIdentifier());

        return null !== $this->user_id && null !== $ownerId && $this->user_id === $ownerId;
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'run_id');
    }

    /**
     * @return HasMany<ImportFailure, $this>
     */
    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class, 'run_id');
    }

    private function normalizeOwnerId(mixed $identifier): ?int
    {
        if (is_int($identifier)) {
            return $identifier;
        }

        if (is_string($identifier) && ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return null;
    }
}
