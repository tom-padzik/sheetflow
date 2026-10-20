<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Contracts;

use Tpadzik\Sheetflow\Support\SpreadsheetPreview;

interface SpreadsheetReaderInterface
{
    public function inspect(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): SpreadsheetPreview;

    /**
     * @return iterable<int, array<string, string|null>>
     */
    public function rows(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): iterable;
}
