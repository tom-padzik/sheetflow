<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

class ValueCensus
{
    /**
     * @param  array<string, array<string, int>>  $counts
     */
    public function __construct(
        private readonly array $counts = [],
    ) {}

    public function occurrences(string $field, mixed $value): int
    {
        if (! is_string($value) || '' === $value) {
            return 0;
        }

        return $this->counts[$field][$value] ?? 0;
    }
}
