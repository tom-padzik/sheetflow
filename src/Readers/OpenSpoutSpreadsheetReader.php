<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Readers;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Exceptions\InvalidSpreadsheetException;
use Tpadzik\Sheetflow\Support\SpreadsheetPreview;

class OpenSpoutSpreadsheetReader implements SpreadsheetReaderInterface
{
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
                    $headers = $this->headersFrom($cells);

                    continue;
                }

                if (null === $headers || $this->cellsAreEmpty($cells)) {
                    continue;
                }

                yield $spreadsheetRow => $this->associate(
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
            throw new InvalidSpreadsheetException('The selected sheet was not found in the file.');
        }

        if ([] === $headers) {
            throw new InvalidSpreadsheetException('The spreadsheet has no header row.');
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
                $headers = $this->headersFrom($cells);

                continue;
            }

            if ($this->cellsAreEmpty($cells)) {
                continue;
            }

            $previewRows[] = $this->associate(headers: $headers, cells: $cells);

            if (count($previewRows) >= $previewLimit) {
                break;
            }
        }

        return [$headers, $previewRows];
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     */
    private function headersFrom(array $cells): array
    {
        $headers = [];

        foreach ($cells as $cell) {
            $header = $this->stringify($cell);

            if (null === $header) {
                if ($this->cellsAreEmpty(array_slice($cells, count($headers)))) {
                    break;
                }

                throw new InvalidSpreadsheetException('The header row has an empty column.');
            }

            $headers[] = $header;
        }

        if ([] === $headers) {
            throw new InvalidSpreadsheetException('The spreadsheet has no header row.');
        }

        $counts = array_count_values($headers);

        foreach ($counts as $header => $count) {
            if ($count > 1) {
                throw new InvalidSpreadsheetException(sprintf('The header row contains a duplicate column "%s".', $header));
            }
        }

        return $headers;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $cells
     * @return array<string, string|null>
     */
    private function associate(array $headers, array $cells): array
    {
        $values = [];

        foreach ($headers as $index => $header) {
            $values[$header] = $this->stringify($cells[$index] ?? null);
        }

        return $values;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function cellsAreEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (null !== $this->stringify($cell)) {
                return false;
            }
        }

        return true;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (null === $value) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $string = (string) $value;
        } else {
            $string = trim((string) $value);
        }

        if ('' === $string) {
            return null;
        }

        return $string;
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

        throw new InvalidSpreadsheetException('The selected sheet was not found in the file.');
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

        throw new InvalidSpreadsheetException(sprintf('The format [%s] is not supported.', $format));
    }
}
