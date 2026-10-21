<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

class UiPaths
{
    public static function stylesheet(): string
    {
        return resource_path('vendor/sheetflow/css/wizard.css');
    }

    public static function script(): string
    {
        return resource_path('vendor/sheetflow/js/wizard.js');
    }

    /**
     * @return array<string, string>
     */
    public static function publishable(): array
    {
        $resources = dirname(__DIR__, 2).'/resources';

        return [
            $resources.'/views/wizard.blade.php' => resource_path('views/vendor/sheetflow/wizard.blade.php'),
            $resources.'/css/wizard.css' => self::stylesheet(),
            $resources.'/js/wizard.js' => self::script(),
            $resources.'/js/index.js' => resource_path('vendor/sheetflow/js/index.js'),
            $resources.'/js/components/SheetflowWizard.vue' => resource_path('vendor/sheetflow/js/components/SheetflowWizard.vue'),
        ];
    }
}
