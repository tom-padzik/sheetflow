<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tpadzik\Sheetflow\Tests\TestCase;

class PublishedWizardAssetsTest extends TestCase
{
    #[Test]
    public function test_serves_published_wizard_assets_instead_of_the_package_files(): void
    {
        $stylesheet = resource_path('vendor/sheetflow/css/wizard.css');
        $script = resource_path('vendor/sheetflow/js/wizard.js');

        try {
            $this->get(route('sheetflow.assets.stylesheet'))
                ->assertOk()
                ->assertSee('.sheetflow', false);

            File::ensureDirectoryExists(dirname($stylesheet));
            File::ensureDirectoryExists(dirname($script));
            File::put($stylesheet, '/* published-sheetflow-style */');
            File::put($script, '/* published-sheetflow-script */');

            $this->get(route('sheetflow.assets.stylesheet'))
                ->assertOk()
                ->assertSee('published-sheetflow-style', false);

            $this->get(route('sheetflow.assets.wizard'))
                ->assertOk()
                ->assertSee('published-sheetflow-script', false);
        } finally {
            File::delete($stylesheet);
            File::delete($script);
        }
    }
}
