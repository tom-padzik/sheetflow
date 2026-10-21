<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Fixtures;

class TemplatesDisabledImport extends ProductImport
{
    public function saveTemplates(): ?bool
    {
        return false;
    }
}
