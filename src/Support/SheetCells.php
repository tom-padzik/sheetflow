<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use DateTimeInterface;
use Tpadzik\Sheetflow\Exceptions\InvalidSpreadsheetException;

class SheetCells
{
    /**
     * @param  list<mixed>  $cells
     * @return list<string>
     *
     * @throws InvalidSpreadsheetException
     */
    public function headers(array $cells): array
    {
        $headers = [];

        foreach ($cells as $cell) {
            $header = $this->stringify($cell);

            if (null === $header) {
                if ($this->areEmpty(array_slice($cells, count($headers)))) {
                    break;
                }

                throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.empty_header'));
            }

            $headers[] = $header;
        }

        if ([] === $headers) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.no_header'));
        }

        $counts = array_count_values($headers);
        $duplicate = ArrayCompat::find(
            array: array_keys($counts),
            callback: fn (string $header): bool => $counts[$header] > 1,
        );

        if (is_string($duplicate)) {
            throw new InvalidSpreadsheetException(__('sheetflow::messages.errors.duplicate_header', [
                'column' => $duplicate,
            ]));
        }

        return $headers;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $cells
     * @return array<string, string|null>
     */
    public function associate(array $headers, array $cells): array
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
    public function areEmpty(array $cells): bool
    {
        return ArrayCompat::all(
            array: $cells,
            callback: fn (mixed $cell): bool => null === $this->stringify($cell),
        );
    }

    public function stringify(mixed $value): ?string
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
}
