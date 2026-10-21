<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tpadzik\Sheetflow\Exceptions\UnknownDefinitionException;
use Tpadzik\Sheetflow\Http\Requests\UpdateMappingRequest;
use Tpadzik\Sheetflow\Http\Requests\UploadImportRequest;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Services\ImportPipeline;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;

class RunController extends SheetflowController
{
    public function __construct(
        private readonly ImportPipeline $pipeline,
        private readonly DefinitionRegistry $definitions,
    ) {}

    public function store(UploadImportRequest $request, string $definition): JsonResponse
    {
        $this->known($definition);
        $this->authorizeDefinition($definition);

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $delimiter = $request->string('delimiter')->toString();
        $sheet = $request->string('sheet')->toString();

        $run = $this->pipeline->start(
            definitionKey: $definition,
            file: $file,
            sheet: '' === $sheet ? null : $sheet,
            delimiter: '' === $delimiter ? null : $delimiter,
        );

        return response()->json($this->pipeline->present($run), 201);
    }

    public function show(ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        return response()->json($this->pipeline->present($run));
    }

    public function updateMapping(UpdateMappingRequest $request, ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $definition = $this->definitions->resolve($run->definition_key);

        if ($request->boolean('save_template') && ! Sheetflow::savesTemplates(definition: $definition)) {
            abort(403);
        }

        /** @var array<string, string|null> $columnMap */
        $columnMap = $request->input('column_map', []);

        $templateName = $request->input('template_name');

        $run = $this->pipeline->saveMapping(
            run: $run,
            columnMap: $columnMap,
            saveTemplate: $request->boolean('save_template'),
            templateName: is_string($templateName) ? $templateName : null,
        );

        return response()->json($this->pipeline->present($run));
    }

    public function updateSheet(Request $request, ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $data = $request->validate([
            'sheet' => ['required', 'string', 'max:255'],
        ]);

        $run = $this->pipeline->restage(
            run: $run,
            sheet: $data['sheet'],
        );

        return response()->json($this->pipeline->present($run));
    }

    public function validateRun(Request $request, ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $data = $request->validate([
            'column_map' => ['sometimes', 'array'],
            'column_map.*' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['column_map']) && is_array($data['column_map'])) {
            /** @var array<string, string|null> $columnMap */
            $columnMap = $data['column_map'];
            $run = $this->pipeline->saveMapping(
                run: $run,
                columnMap: $columnMap,
                saveTemplate: false,
                templateName: null,
            );
        }

        $run = $this->pipeline->startValidation($run);

        return response()->json($this->pipeline->present($run));
    }

    public function import(ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $run = $this->pipeline->startImport($run);

        return response()->json($this->pipeline->present($run));
    }

    public function cancel(ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $run = $this->pipeline->cancel($run);

        return response()->json($this->pipeline->present($run));
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
