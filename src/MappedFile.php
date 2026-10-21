<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

class MappedFile
{
    /**
     * @param  list<MappedRow>  $rows
     */
    public function __construct(
        private readonly array $rows,
    ) {}

    /**
     * @return list<MappedRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<array{row: int, value: mixed}>
     */
    public function column(string $field): array
    {
        $column = [];

        foreach ($this->rows as $row) {
            $column[] = [
                'row' => $row->rowNumber,
                'value' => $row->value(field: $field),
            ];
        }

        return $column;
    }

    /**
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    public function values(array $fields): array
    {
        $table = [];

        foreach ($this->rows as $row) {
            $entry = ['row' => $row->rowNumber];

            foreach ($fields as $field) {
                $entry[$field] = $row->value(field: $field);
            }

            $table[] = $entry;
        }

        return $table;
    }
}
