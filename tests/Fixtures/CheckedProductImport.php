<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\MappedRow;

class CheckedProductImport extends ProductImport
{
    public function validateRow(MappedRow $row): array
    {
        $errors = [];
        $sku = $row->value(field: 'sku');

        if (is_string($sku) && $row->occurrences(field: 'sku') > 1) {
            $errors['sku'][] = 'This ID is repeated in the file.';
        }

        if (is_string($sku) && '' !== $sku && Product::query()->where('sku', $sku)->exists()) {
            $errors['sku'][] = 'This ID already exists.';
        }

        return $errors;
    }
}
