<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class AllowProducts
{
    public function __invoke(?Authenticatable $user, string $definition): bool
    {
        return 'products' === $definition;
    }
}
