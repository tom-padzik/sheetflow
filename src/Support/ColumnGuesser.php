<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use Tpadzik\Sheetflow\Field;

class ColumnGuesser
{
    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public function guess(array $fields, array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $header) {
            $normalizedHeaders[$this->normalize($header)] = $header;
        }

        $map = [];

        foreach ($fields as $field) {
            $map[$field->name()] = $this->matchField(
                field: $field,
                normalizedHeaders: $normalizedHeaders,
            );
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $normalizedHeaders
     */
    private function matchField(Field $field, array $normalizedHeaders): ?string
    {
        $candidates = array_merge(
            [$field->name(), $field->displayLabel()],
            $field->guesses(),
        );

        $candidate = ArrayCompat::find(
            array: $candidates,
            callback: function (string $candidate) use ($normalizedHeaders): bool {
                $key = $this->normalize($candidate);

                return '' !== $key && array_key_exists($key, $normalizedHeaders);
            },
        );

        if (! is_string($candidate)) {
            return null;
        }

        return $normalizedHeaders[$this->normalize($candidate)];
    }

    private function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $stripped = preg_replace('/[^a-z0-9]+/', '', $normalized);

        return is_string($stripped) ? $stripped : '';
    }
}
