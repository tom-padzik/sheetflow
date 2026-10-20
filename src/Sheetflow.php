<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Tpadzik\Sheetflow\Support\DefinitionRegistry;

class Sheetflow
{
    /**
     * @param  class-string<ImportDefinition>  $definition
     */
    public static function definition(string $key, string $definition): void
    {
        app(DefinitionRegistry::class)->register(
            key: $key,
            definition: $definition,
        );
    }
}
