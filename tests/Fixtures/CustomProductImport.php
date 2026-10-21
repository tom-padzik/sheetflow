<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\MappedRow;

class CustomProductImport extends ProductImport
{
    protected function persist(MappedRow $row): void
    {
        Product::query()->create([
            'id' => sprintf('custom-%s', $row->attributes()['id']),
            'name' => $row->attributes()['name'],
            'price' => $row->attributes()['price'],
        ]);
    }
}
