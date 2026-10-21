<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpadzik\Sheetflow\Support\ArrayCompat;

class ArrayCompatTest extends TestCase
{
    #[Test]
    public function test_matches_the_language_functions_when_they_exist(): void
    {
        $values = ['a' => 1, 'b' => 2];
        $isPositive = fn (int $value): bool => $value > 0;
        $isTwo = fn (int $value): bool => 2 === $value;

        if (function_exists('array_all')) {
            $this->assertSame(array_all($values, $isPositive), ArrayCompat::all(array: $values, callback: $isPositive));
        }

        if (function_exists('array_any')) {
            $this->assertSame(array_any($values, $isTwo), ArrayCompat::any(array: $values, callback: $isTwo));
        }

        if (function_exists('array_find')) {
            $this->assertSame(array_find($values, $isTwo), ArrayCompat::find(array: $values, callback: $isTwo));
        }

        $this->assertTrue(ArrayCompat::all(array: [], callback: fn (mixed $value): bool => false));
        $this->assertFalse(ArrayCompat::any(array: [], callback: fn (mixed $value): bool => true));
        $this->assertNull(ArrayCompat::find(array: $values, callback: fn (int $value): bool => 3 === $value));
        $this->assertSame(2, ArrayCompat::find(array: $values, callback: fn (int $value, string $key): bool => 'b' === $key));
    }

    #[Test]
    public function test_emulates_the_same_results_without_the_language_functions(): void
    {
        $values = ['a' => 0, 'b' => 2];

        $this->assertTrue(ArrayCompat::allEmulated(array: [], callback: fn (mixed $value): bool => false));
        $this->assertFalse(ArrayCompat::allEmulated(array: $values, callback: fn (int $value): bool => $value > 0));
        $this->assertTrue(ArrayCompat::allEmulated(array: [1, 2], callback: fn (int $value): bool => $value > 0));

        $this->assertFalse(ArrayCompat::anyEmulated(array: [], callback: fn (mixed $value): bool => true));
        $this->assertTrue(ArrayCompat::anyEmulated(array: $values, callback: fn (int $value): bool => $value > 0));
        $this->assertFalse(ArrayCompat::anyEmulated(array: [0], callback: fn (int $value): bool => $value > 0));

        $this->assertSame(2, ArrayCompat::findEmulated(array: $values, callback: fn (int $value, string $key): bool => 'b' === $key));
        $this->assertNull(ArrayCompat::findEmulated(array: $values, callback: fn (int $value): bool => 3 === $value));
        $this->assertNull(ArrayCompat::findEmulated(array: ['x' => null], callback: fn (mixed $value): bool => null === $value));

        if (function_exists('array_all')) {
            $this->assertSame(
                array_all($values, fn (int $value): bool => $value > 0),
                ArrayCompat::allEmulated(array: $values, callback: fn (int $value): bool => $value > 0),
            );
        }

        if (function_exists('array_any')) {
            $this->assertSame(
                array_any($values, fn (int $value): bool => $value > 0),
                ArrayCompat::anyEmulated(array: $values, callback: fn (int $value): bool => $value > 0),
            );
        }

        if (function_exists('array_find')) {
            $this->assertSame(
                array_find($values, fn (int $value, string $key): bool => 'b' === $key),
                ArrayCompat::findEmulated(array: $values, callback: fn (int $value, string $key): bool => 'b' === $key),
            );
        }
    }
}
