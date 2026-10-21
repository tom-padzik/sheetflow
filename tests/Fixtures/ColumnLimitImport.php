<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\MappedFile;

class ColumnLimitImport extends ProductImport
{
    public function validateFile(MappedFile $file): array
    {
        $errors = [];

        foreach ($file->column(field: 'price') as $entry) {
            if ((float) $entry['value'] > 100) {
                $errors[$entry['row']]['price'][] = 'This price is over 100.';
            }
        }

        return $errors;
    }
}
