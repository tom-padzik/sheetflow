<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

class CheckedRow
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        public readonly ?array $errors,
        public readonly MappedRow $row,
        public readonly bool $stopsImport,
    ) {}
}
