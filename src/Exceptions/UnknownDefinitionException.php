<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Exceptions;

use RuntimeException;

class UnknownDefinitionException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Import definition [%s] is not registered.', $key));
    }
}
