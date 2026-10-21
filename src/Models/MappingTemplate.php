<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $definition_key
 * @property string $name
 * @property string $header_signature
 * @property array<string, string|null> $column_map
 * @property array<string, string>|null $invalid_actions
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MappingTemplate extends Model
{
    protected $table = 'sheetflow_mapping_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'definition_key',
        'name',
        'header_signature',
        'column_map',
        'invalid_actions',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'invalid_actions' => 'array',
        ];
    }
}
