<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Readers;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Exceptions\InvalidSpreadsheetException;
use Tpadzik\Sheetflow\Support\SheetCells;
use Tpadzik\Sheetflow\Support\SpreadsheetPreview;

class XlsSpreadsheetReader implements SpreadsheetReaderInterface
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
        $spreadsheet = $this->load($path);

        try {
            $sheetNames = $spreadsheet->getSheetNames();
            $worksheet = $this->selectSheet(spreadsheet: $spreadsheet, sheet: $sheet);
            $width = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
            $headerCells = $this->rowValues(worksheet: $worksheet, rowNumber: 1, columns: $width);
            $headers = $this->cells->headers($headerCells);
            $previewRows = [];
            $previewLimit = (int) config('sheetflow.preview_rows', 20);
            $lastRow = $worksheet->getHighestDataRow();

            for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                $values = $this->rowValues(
                    worksheet: $worksheet,
                    rowNumber: $rowNumber,
                    columns: count($headers),
                );

                if ($this->cells->areEmpty($values)) {
                    continue;
                }

                $previewRows[] = $this->cells->associate(headers: $headers, cells: $values);

                if (count($previewRows) >= $previewLimit) {
                    break;
                }
            }

            return new SpreadsheetPreview(
                headers: $headers,
                sheetNames: $sheetNames,
                previewRows: $previewRows,
                delimiter: null,
                sheet: $worksheet->getTitle(),
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function rows(
        string $path,
        string $format,
        ?string $delimiter,
        ?string $sheet,
    ): iterable {
        $spreadsheet = $this->load($path);

        try {
            $worksheet = $this->selectSheet(spreadsheet: $spreadsheet, sheet: $sheet);
            $width = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
            $headers = $this->cells->headers($this->rowValues(
                worksheet: $worksheet,
                rowNumber: 1,
                columns: $width,
            ));
            $lastRow = $worksheet->getHighestDataRow();

            for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                $values = $this->rowValues(
                    worksheet: $worksheet,
                    rowNumber: $rowNumber,
                    columns: count($headers),
                );

                if ($this->cells->areEmpty($values)) {
                    continue;
                }

                yield $rowNumber => $this->cells->associate(headers: $headers, cells: $values);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function load(string $path): Spreadsheet
    {
        try {
            $reader = IOFactory::createReader('Xls');
            $reader->setReadDataOnly(false);

            return $reader->load($path);
        } catch (Throwable) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.unreadable'));
        }
    }

    /**
     * @throws InvalidSpreadsheetException
     */
    private function selectSheet(Spreadsheet $spreadsheet, ?string $sheet): Worksheet
    {
        $wanted = null === $sheet || '' === $sheet ? null : $sheet;

        if (null === $wanted) {
            return $spreadsheet->getSheet(0);
        }

        $worksheet = $spreadsheet->getSheetByName($wanted);

        if (null === $worksheet) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.sheet_missing'));
        }

        return $worksheet;
    }

    /**
     * @return list<mixed>
     */
    private function rowValues(Worksheet $worksheet, int $rowNumber, int $columns): array
    {
        $values = [];

        for ($column = 1; $column <= $columns; $column++) {
            $coordinate = Coordinate::stringFromColumnIndex($column).$rowNumber;
            $values[] = $this->cellValue($worksheet->getCell($coordinate));
        }

        return $values;
    }

    private function cellValue(Cell $cell): mixed
    {
        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable) {
            $value = $cell->getValue();
        }

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_numeric($value) && Date::isDateTime($cell)) {
            return Date::excelToDateTimeObject((float) $value);
        }

        return $value;
    }
}
