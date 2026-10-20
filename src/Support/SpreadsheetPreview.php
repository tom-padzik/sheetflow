<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

class SpreadsheetPreview
{
    /**
     * @param  list<string>  $headers
     * @param  list<string>  $sheetNames
     * @param  list<array<string, string|null>>  $previewRows
     */
    public function __construct(
        public readonly array $headers,
        public readonly array $sheetNames,
        public readonly array $previewRows,
        public readonly ?string $delimiter,
        public readonly string $sheet,
    ) {}
}
