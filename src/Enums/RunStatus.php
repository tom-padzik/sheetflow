<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Enums;

enum RunStatus: string
{
    case uploaded = 'uploaded';
    case staging = 'staging';
    case staged = 'staged';
    case validating = 'validating';
    case validated = 'validated';
    case importing = 'importing';
    case completed = 'completed';
    case failed = 'failed';
    case cancelled = 'cancelled';
}
