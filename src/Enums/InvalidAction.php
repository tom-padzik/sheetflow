<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Enums;

enum InvalidAction: string
{
    case SkipRow = 'skip_row';
    case Null = 'null';
    case Stop = 'stop';
}
