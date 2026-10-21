<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\Field;
use Tpadzik\Sheetflow\ImportDefinition;

class ProductImport extends ImportDefinition
{
    public function model(): string
    {
        return Product::class;
    }

    public function fields(): array
    {
        return [
            Field::make('id')->label('ID')->rules(['required', 'string'])->matchOn()->guess(['kod']),
            Field::make('name')->rules(['required', 'string']),
            Field::make('price')->rules(['numeric']),
        ];
    }
}
