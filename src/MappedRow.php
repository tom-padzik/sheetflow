<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Tpadzik\Sheetflow\Support\ValueCensus;

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
        private readonly ValueCensus $census = new ValueCensus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function value(string $field): mixed
    {
        return $this->attributes[$field] ?? null;
    }

    /**
     * How many rows in this file have the same non-empty value for the field.
     */
    public function occurrences(string $field): int
    {
        return $this->census->occurrences(field: $field, value: $this->value(field: $field));
    }

    public function withNull(string $field): self
    {
        $attributes = $this->attributes;
        $attributes[$field] = null;

        return new self(
            rowNumber: $this->rowNumber,
            raw: $this->raw,
            attributes: $attributes,
            census: $this->census,
        );
    }
}
