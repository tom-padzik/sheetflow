<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Readers;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Exceptions\InvalidSpreadsheetException;
use Tpadzik\Sheetflow\Support\SheetCells;
use Tpadzik\Sheetflow\Support\SpreadsheetPreview;

class OpenSpoutSpreadsheetReader implements SpreadsheetReaderInterface
{
    public function __construct(
        private readonly SheetCells $cells,
    ) {}

    public function inspect(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): SpreadsheetPreview {
        $resolvedDelimiter = $this->resolveDelimiter(
            path: $path,
            format: $format,
            delimiter: $delimiter,
        );
        $reader = $this->open(
            path: $path,
            format: $format,
            delimiter: $resolvedDelimiter,
        );

        try {
            return $this->inspectReader(
                reader: $reader,
                sheet: $sheet,
                delimiter: $resolvedDelimiter,
            );
        } finally {
            $reader->close();
        }
    }

    public function rows(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): iterable {
        $reader = $this->open(
            path: $path,
            format: $format,
            delimiter: $delimiter,
        );

        try {
            $selected = $this->selectSheet(reader: $reader, sheet: $sheet);
            $headers = null;
            $spreadsheetRow = 0;

            foreach ($selected->getRowIterator() as $row) {
                $spreadsheetRow++;
                $cells = $row->toArray();

                if (1 === $spreadsheetRow) {
                    $headers = $this->cells->headers($cells);

                    continue;
                }

                if (null === $headers || $this->cells->areEmpty($cells)) {
                    continue;
                }

                yield $spreadsheetRow => $this->cells->associate(
                    headers: $headers,
                    cells: $cells,
                );
            }
        } finally {
            $reader->close();
        }
    }

    private function inspectReader(
        ReaderInterface $reader,
        ?string $sheet,
        ?string $delimiter,
    ): SpreadsheetPreview {
        $sheetNames = [];
        $headers = [];
        $previewRows = [];
        $resolvedSheet = null;
        $isFirst = true;
        $previewLimit = (int) config('sheetflow.preview_rows', 20);

        foreach ($reader->getSheetIterator() as $current) {
            $sheetNames[] = $current->getName();

            if (! $this->sheetIsSelected(name: $current->getName(), wanted: $sheet, isFirst: $isFirst)) {
                $isFirst = false;

                continue;
            }

            $isFirst = false;
            $resolvedSheet = $current->getName();
            [$headers, $previewRows] = $this->readHead(
                sheet: $current,
                previewLimit: $previewLimit,
            );
        }

        if (null === $resolvedSheet) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.sheet_missing'));
        }

        if ([] === $headers) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.no_header'));
        }

        return new SpreadsheetPreview(
            headers: $headers,
            sheetNames: $sheetNames,
            previewRows: $previewRows,
            delimiter: $delimiter,
            sheet: $resolvedSheet,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, string|null>>}
     */
    private function readHead(SheetInterface $sheet, int $previewLimit): array
    {
        $headers = [];
        $previewRows = [];
        $spreadsheetRow = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $spreadsheetRow++;
            $cells = $row->toArray();

            if (1 === $spreadsheetRow) {
                $headers = $this->cells->headers($cells);

                continue;
            }

            if ($this->cells->areEmpty($cells)) {
                continue;
            }

            $previewRows[] = $this->cells->associate(headers: $headers, cells: $cells);

            if (count($previewRows) >= $previewLimit) {
                break;
            }
        }

        return [$headers, $previewRows];
    }

    private function sheetIsSelected(string $name, ?string $wanted, bool $isFirst): bool
    {
        if (null === $wanted || '' === $wanted) {
            return $isFirst;
        }

        return $name === $wanted;
    }

    private function selectSheet(ReaderInterface $reader, ?string $sheet): SheetInterface
    {
        $isFirst = true;

        foreach ($reader->getSheetIterator() as $current) {
            if ($this->sheetIsSelected(name: $current->getName(), wanted: $sheet, isFirst: $isFirst)) {
                return $current;
            }

            $isFirst = false;
        }

        throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.sheet_missing'));
    }

    private function resolveDelimiter(string $path, string $format, ?string $delimiter): ?string
    {
        if ('csv' !== $format && 'txt' !== $format) {
            return null;
        }

        if (null !== $delimiter && '' !== $delimiter) {
            return $delimiter;
        }

        $sample = file_get_contents($path, false, null, 0, 8192);

        if (! is_string($sample) || '' === $sample) {
            return ',';
        }

        $line = preg_split("/\r\n|\n|\r/", $sample)[0] ?? '';
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $winner = array_key_first($counts);

        if (! is_string($winner) || 0 === $counts[$winner]) {
            return ',';
        }

        return $winner;
    }

    private function open(string $path, string $format, ?string $delimiter): ReaderInterface
    {
        $reader = $this->makeReader(format: $format, delimiter: $delimiter);
        $reader->open($path);

        return $reader;
    }

    private function makeReader(string $format, ?string $delimiter): ReaderInterface
    {
        if ('csv' === $format || 'txt' === $format) {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = $delimiter ?? ',';
            $options->ENCODING = 'UTF-8';

            return new CsvReader($options);
        }

        if ('xlsx' === $format) {
            return new XlsxReader;
        }

        if ('ods' === $format) {
            return new OdsReader;
        }

        throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.unsupported_format', [
            'format' => $format,
        ]));
    }
}
