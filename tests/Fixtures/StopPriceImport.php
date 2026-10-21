<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\Enums\InvalidAction;
use Tpadzik\Sheetflow\Field;

class StopPriceImport extends ProductImport
{
    public function fields(): array
    {
        return [
            Field::make('id')->label('ID')->rules(['required', 'string'])->matchOn()->guess(['kod']),
            Field::make('name')->rules(['required', 'string']),
            Field::make('price')->rules(['numeric'])->onInvalid(action: InvalidAction::Stop),
        ];
    }
}
