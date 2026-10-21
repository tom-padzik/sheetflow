<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Tpadzik\Sheetflow\Exceptions\UnknownDefinitionException;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;
use Tpadzik\Sheetflow\Support\UiPaths;

class WizardController extends SheetflowController
{
    public function __construct(
        private readonly DefinitionRegistry $definitions,
    ) {}

    public function show(string $definition): View
    {
        $this->known($definition);
        $this->authorizeDefinition($definition);

        return view('sheetflow::wizard', [
            'definition' => $definition,
            'apiBase' => url(trim((string) config('sheetflow.prefix'), '/').'/api'),
        ]);
    }

    public function script(): Response
    {
        return $this->asset(
            published: UiPaths::script(),
            packaged: dirname(__DIR__, 3).'/resources/js/wizard.js',
            contentType: 'text/javascript; charset=UTF-8',
        );
    }

    public function stylesheet(): Response
    {
        return $this->asset(
            published: UiPaths::stylesheet(),
            packaged: dirname(__DIR__, 3).'/resources/css/wizard.css',
            contentType: 'text/css; charset=UTF-8',
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

    private function asset(string $published, string $packaged, string $contentType): Response
    {
        $path = is_file($published) ? $published : $packaged;

        return response(
            (string) file_get_contents($path),
            200,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'no-cache',
            ],
        );
    }
}
