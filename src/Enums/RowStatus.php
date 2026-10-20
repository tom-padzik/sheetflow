<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Enums;

enum RowStatus: string
{
    case pending = 'pending';
    case valid = 'valid';
    case invalid = 'invalid';
    case imported = 'imported';
}
