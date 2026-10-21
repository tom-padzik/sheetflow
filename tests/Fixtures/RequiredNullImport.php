<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

use Tpadzik\Sheetflow\Enums\InvalidAction;
use Tpadzik\Sheetflow\Field;

class RequiredNullImport extends ProductImport
{
    public function fields(): array
    {
        return [
            Field::make('sku')->label('ID')->rules(['required', 'string'])->matchOn()->guess(['kod'])->onInvalid(action: InvalidAction::Null),
            Field::make('name')->rules(['required', 'string']),
            Field::make('price')->rules(['numeric']),
        ];
    }
}
