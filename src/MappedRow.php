<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

class MappedRow
{
    /**
     * @param  array<string, string|null>  $raw
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $raw,
        private readonly array $attributes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
