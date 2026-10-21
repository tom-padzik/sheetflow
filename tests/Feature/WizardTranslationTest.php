<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Tests\Fixtures\ProductImport;
use Tpadzik\Sheetflow\Tests\TestCase;

class WizardTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sheetflow::definition(key: 'products', definition: ProductImport::class);
    }

    #[Test]
    public function test_renders_the_english_wizard_and_returns_english_labels(): void
    {
        app()->setLocale('en');

        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk()
            ->assertSee('Import spreadsheet', false)
            ->assertSee('Upload a CSV, XLS, XLSX, or ODS file', false);

        $this->getJson(route('sheetflow.translations'))
            ->assertOk()
            ->assertJsonPath('title', 'Import spreadsheet')
            ->assertJsonPath('statuses.staged', 'Ready')
            ->assertJsonPath('errors.file_not_stored', 'The file could not be stored.')
            ->assertJsonPath('validated_ready', 'Validation finished. All :valid rows are valid. You can import them.')
            ->assertJsonPath('imported_ready', 'Import finished. All :imported rows were imported.')
            ->assertJsonPath('invalid_actions.skip_row', 'Skip row')
            ->assertJsonPath('load_mapping', 'Load mapping')
            ->assertJsonPath('import_stopped', 'Import stopped at row :row. No rows were imported.');
    }

    #[Test]
    public function test_returns_german_labels_for_the_german_locale(): void
    {
        app()->setLocale('de');

        $this->getJson(route('sheetflow.translations'))
            ->assertOk()
            ->assertJsonPath('title', 'Tabelle importieren')
            ->assertJsonPath('statuses.staging', 'Wird gelesen');
    }

    #[Test]
    public function test_falls_back_to_english_when_the_locale_has_no_sheetflow_file(): void
    {
        app()->setLocale('fr');

        $this->getJson(route('sheetflow.translations'))
            ->assertOk()
            ->assertJsonPath('title', 'Import spreadsheet');
    }
}
