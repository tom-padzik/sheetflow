<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Tpadzik\Sheetflow\Exceptions\UnknownDefinitionException;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;

class WizardController extends SheetflowController
{
    public function __construct(
        private readonly DefinitionRegistry $definitions,
    ) {}

    public function show(string $definition): View
    {
        $this->known($definition);

        return view('sheetflow::wizard', [
            'definition' => $definition,
            'apiBase' => url(trim((string) config('sheetflow.prefix'), '/').'/api'),
        ]);
    }

    public function script(): Response
    {
        return response(
            (string) file_get_contents(__DIR__.'/../../../resources/js/wizard.js'),
            200,
            ['Content-Type' => 'text/javascript; charset=UTF-8'],
        );
    }

    public function stylesheet(): Response
    {
        return response(
            (string) file_get_contents(__DIR__.'/../../../resources/css/wizard.css'),
            200,
            ['Content-Type' => 'text/css; charset=UTF-8'],
        );
    }

    private function known(string $definition): void
    {
        try {
            $this->definitions->resolve($definition);
        } catch (UnknownDefinitionException) {
            abort(404);
        }
    }
}
