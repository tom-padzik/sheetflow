<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Tpadzik\Sheetflow\Models\ImportRun;

class FailureController extends SheetflowController
{
    public function index(ImportRun $run): JsonResponse
    {
        $this->authorizeRun($run);

        $failures = $run->failures()
            ->orderBy('row_number')
            ->paginate(perPage: 20)
            ->withQueryString();

        return response()->json([
            'data' => $failures->items(),
            'current_page' => $failures->currentPage(),
            'last_page' => $failures->lastPage(),
            'total' => $failures->total(),
        ]);
    }
}
