<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Http\JsonResponse;

class TranslationController extends SheetflowController
{
    public function show(): JsonResponse
    {
        $messages = __('sheetflow::messages');

        if (! is_array($messages)) {
            return response()->json();
        }

        return response()->json($messages);
    }
}
