<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\MappedFile;

class SumByIdImport extends ProductImport
{
    public static int $fileChecks = 0;

    public function validateFile(MappedFile $file): array
    {
        self::$fileChecks++;
        $totals = [];

        foreach ($file->values(fields: ['id', 'price']) as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $totals[$id] = ($totals[$id] ?? 0) + (float) ($entry['price'] ?? 0);
        }

        $errors = [];

        foreach ($file->rows() as $row) {
            $id = (string) $row->value(field: 'id');

            if (($totals[$id] ?? 0) > 200) {
                $errors[$row->rowNumber]['price'][] = 'The sum for this ID is over 200.';
            }
        }

        return $errors;
    }
}
