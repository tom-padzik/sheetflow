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
    public function test_renders_the_polish_wizard_and_returns_polish_labels(): void
    {
        app()->setLocale('pl');

        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk()
            ->assertSee('Import arkusza', false)
            ->assertSee('Wgraj plik CSV, XLS, XLSX lub ODS', false);

        $this->getJson(route('sheetflow.translations'))
            ->assertOk()
            ->assertJsonPath('title', 'Import arkusza')
            ->assertJsonPath('statuses.staged', 'Gotowy')
            ->assertJsonPath('errors.file_not_stored', 'Nie udało się zapisać pliku.')
            ->assertJsonPath('validated_ready', 'Sprawdzanie zakończone. Wszystkie wiersze są poprawne (:valid). Możesz je zaimportować.')
            ->assertJsonPath('imported_ready', 'Import zakończony. Wszystkie wiersze zostały zaimportowane (:imported).');
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
