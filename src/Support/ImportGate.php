<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use Tpadzik\Sheetflow\ImportDefinition;

class ImportGate
{
    private mixed $authorizer = null;

    public function set(callable|string $authorizer): void
    {
        $this->authorizer = $authorizer;
    }

    public function decide(string $definitionKey, ImportDefinition $definition): void
    {
        if ($this->allowsEveryone()) {
            return;
        }

        $global = $this->globalDecision($definitionKey);
        $local = $definition->authorize(auth()->user());

        if (null !== $local) {
            $local = true === $local;
        }

        if (null === $global && null === $local) {
            return;
        }

        if (true === $global || true === $local) {
            return;
        }

        abort(403);
    }

    private function allowsEveryone(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('sheetflow.allow_all', false);
    }

    private function globalDecision(string $definitionKey): ?bool
    {
        if (null === $this->authorizer) {
            return null;
        }

        $authorizer = $this->authorizer;

        if (is_string($authorizer)) {
            $authorizer = app($authorizer);
        }

        return true === $authorizer(auth()->user(), $definitionKey);
    }
}
