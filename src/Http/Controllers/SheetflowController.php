<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Routing\Controller;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;
use Tpadzik\Sheetflow\Support\ImportGate;

abstract class SheetflowController extends Controller
{
    protected function authorizeDefinition(string $definition): void
    {
        app(ImportGate::class)->decide(
            definitionKey: $definition,
            definition: app(DefinitionRegistry::class)->resolve($definition),
        );
    }

    protected function authorizeRun(ImportRun $run): void
    {
        $this->authorizeDefinition($run->definition_key);

        if (! $run->ownedBy(auth()->user())) {
            abort(403);
        }
    }
}
