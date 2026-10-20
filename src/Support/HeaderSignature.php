<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

class HeaderSignature
{
    /**
     * @param  list<string>  $headers
     */
    public function make(array $headers): string
    {
        return hash('sha256', implode("\0", $headers));
    }
}
