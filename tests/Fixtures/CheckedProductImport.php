<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\MappedRow;

class CheckedProductImport extends ProductImport
{
    public function validateRow(MappedRow $row): array
    {
        $errors = [];
        $id = $row->value(field: 'id');

        if (is_string($id) && $row->occurrences(field: 'id') > 1) {
            $errors['id'][] = 'This ID is repeated in the file.';
        }

        if (is_string($id) && '' !== $id && Product::query()->where('id', $id)->exists()) {
            $errors['id'][] = 'This ID already exists.';
        }

        return $errors;
    }
}
