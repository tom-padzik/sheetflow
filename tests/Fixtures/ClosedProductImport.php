<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class ClosedProductImport extends ProductImport
{
    public function authorize(?Authenticatable $user): ?bool
    {
        return false;
    }
}
