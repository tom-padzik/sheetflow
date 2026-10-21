<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Readers;

use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Support\SpreadsheetPreview;

class SpreadsheetReader implements SpreadsheetReaderInterface
{
    public function __construct(
        private readonly OpenSpoutSpreadsheetReader $openSpout,
        private readonly XlsSpreadsheetReader $xls,
    ) {}

    public function inspect(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): SpreadsheetPreview {
        return $this->reader($format)->inspect(
            path: $path,
            format: $format,
            delimiter: $delimiter,
            sheet: $sheet,
        );
    }

    public function rows(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): iterable {
        return $this->reader($format)->rows(
            path: $path,
            format: $format,
            delimiter: $delimiter,
            sheet: $sheet,
        );
    }

    private function reader(string $format): SpreadsheetReaderInterface
    {
        if ('xls' === $format) {
            return $this->xls;
        }

        return $this->openSpout;
    }
}
