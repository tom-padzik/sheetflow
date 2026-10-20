<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tpadzik\Sheetflow\Exceptions\UnknownDefinitionException;
use Tpadzik\Sheetflow\Models\MappingTemplate;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;

class TemplateController extends SheetflowController
{
    public function __construct(
        private readonly DefinitionRegistry $definitions,
    ) {}

    public function index(Request $request, string $definition): JsonResponse
    {
        try {
            $this->definitions->resolve($definition);
        } catch (UnknownDefinitionException) {
            abort(404);
        }

        $signature = $request->string('header_signature')->toString();
        $query = MappingTemplate::query()->where('definition_key', $definition);

        if ('' !== $signature) {
            $query->where('header_signature', $signature);
        }

        $userId = auth()->id();

        if (null === $userId) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $userId);
        }

        $templates = $query->orderBy('name')->get(['id', 'name', 'column_map', 'header_signature']);

        return response()->json(['data' => $templates]);
    }
}
