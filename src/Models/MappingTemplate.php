<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Models;

use Illuminate\Database\Eloquent\Model;

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
