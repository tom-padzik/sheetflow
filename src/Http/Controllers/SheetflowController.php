<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Controllers;

use Illuminate\Routing\Controller;
use Tpadzik\Sheetflow\Models\ImportRun;

abstract class SheetflowController extends Controller
{
    protected function authorizeRun(ImportRun $run): void
    {
        $userId = auth()->id();

        if (null === $userId) {
            if (null !== $run->user_id) {
                abort(403);
            }

            return;
        }

        if ((string) $run->user_id !== (string) $userId) {
            abort(403);
        }
    }
}
