<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use Tpadzik\Sheetflow\Field;
use Tpadzik\Sheetflow\MappedRow;

class RowMapper
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, string|null>  $columnMap
     * @param  array<string, string|null>  $raw
     */
    public function map(int $rowNumber, array $raw, array $fields, array $columnMap): MappedRow
    {
        $attributes = [];

        foreach ($fields as $field) {
            $header = $columnMap[$field->name()] ?? null;

            if (! is_string($header) || '' === $header) {
                continue;
            }

            $attributes[$field->name()] = $raw[$header] ?? null;
        }

        return new MappedRow(
            rowNumber: $rowNumber,
            raw: $raw,
            attributes: $attributes,
        );
    }
}
