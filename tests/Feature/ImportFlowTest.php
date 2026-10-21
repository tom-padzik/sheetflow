<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PHPUnit\Framework\Attributes\Test;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Tests\Fixtures\CheckedProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\ChoicePriceImport;
use Tpadzik\Sheetflow\Tests\Fixtures\ColumnLimitImport;
use Tpadzik\Sheetflow\Tests\Fixtures\CustomProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\NullablePriceImport;
use Tpadzik\Sheetflow\Tests\Fixtures\Product;
use Tpadzik\Sheetflow\Tests\Fixtures\ProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\RequiredNullImport;
use Tpadzik\Sheetflow\Tests\Fixtures\StopPriceImport;
use Tpadzik\Sheetflow\Tests\Fixtures\SumByIdImport;
use Tpadzik\Sheetflow\Tests\Fixtures\TemplatesDisabledImport;
use Tpadzik\Sheetflow\Tests\Fixtures\TemplatesEnabledImport;
use Tpadzik\Sheetflow\Tests\Fixtures\UniqueNullablePriceImport;
use Tpadzik\Sheetflow\Tests\Fixtures\UniqueProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\User;
use Tpadzik\Sheetflow\Tests\TestCase;

class ImportFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sheetflow::definition(key: 'products', definition: ProductImport::class);
        Sheetflow::definition(key: 'custom-products', definition: CustomProductImport::class);

        Schema::create('products', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->decimal('price', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    #[Test]
    public function test_guesses_columns_and_previews_the_uploaded_file(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,9.50
        CSV);

        $this->assertSame('kod', $run['column_map']['id']);
        $this->assertSame('name', $run['column_map']['name']);
        $this->assertSame('staged', $run['status']);
        $this->assertCount(1, $run['preview_rows']);
        $this->assertSame(1, $run['total_rows']);
    }

    #[Test]
    public function test_detects_a_semicolon_delimiter(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        ID;name;price
        A-1;Widget;3
        CSV);

        $this->assertSame(';', $run['delimiter']);
        $this->assertSame('Widget', $run['preview_rows'][0]['name']);
    }

    #[Test]
    public function test_rejects_an_unknown_definition(): void
    {
        $file = UploadedFile::fake()->createWithContent('products.csv', <<<'CSV'
        ID,name,price
        A,B,1
        CSV);

        $this->post(route('sheetflow.uploads.store', ['definition' => 'missing']), [
            'file' => $file,
        ])->assertNotFound();
    }

    #[Test]
    public function test_keeps_invalid_rows_out_of_the_database_and_imports_the_valid_ones(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,9.50
        ,Missing,nope
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame('validated', $validated['status']);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(1, $validated['failed_rows']);
        $this->assertSame(0, Product::query()->count());

        $failures = $this->getJson(route('sheetflow.runs.failures', ['run' => $run['id']]))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($failures);
        $this->assertCount(1, $failures);
        $this->assertSame(3, $failures[0]['row_number']);

        $imported = $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($imported);
        $this->assertSame('completed', $imported['status']);
        $this->assertSame(1, $imported['imported_rows']);
        $this->assertTrue(Product::query()->where('id', 'A-1')->exists());
    }

    #[Test]
    public function test_updates_an_existing_row_when_the_match_field_is_the_same(): void
    {
        Product::query()->create([
            'id' => 'A-1',
            'name' => 'Old',
            'price' => 1,
        ]);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,New,4
        CSV);
        $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))->assertOk();
        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertSame(1, Product::query()->count());
        $this->assertSame('New', Product::query()->first()->name);
    }

    #[Test]
    public function test_uses_a_custom_persist_method_instead_of_the_default_upsert(): void
    {
        $file = UploadedFile::fake()->createWithContent('products.csv', <<<'CSV'
        ID,name,price
        A-1,Widget,2
        CSV);

        $run = $this->post(route('sheetflow.uploads.store', ['definition' => 'custom-products']), [
            'file' => $file,
        ])->assertCreated()->json();

        $this->assertIsArray($run);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))->assertOk();
        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertTrue(Product::query()->where('id', 'custom-A-1')->exists());
    }

    #[Test]
    public function test_stores_a_mapping_template_and_offers_it_for_the_same_headers(): void
    {
        $first = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $first['id']]))->assertOk();

        $saved = $this->putJson(route('sheetflow.runs.mapping', ['run' => $first['id']]), [
            'column_map' => $first['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertOk()->json();

        $this->assertIsArray($saved);
        $this->assertSame('validated', $saved['status']);

        $second = $this->uploadCsv(<<<'CSV'
        kod,name,price
        B-2,Other,3
        CSV);

        $this->assertCount(1, $second['templates']);
        $this->assertSame('Shop', $second['templates'][0]['name']);
        $this->assertSame('kod', $second['templates'][0]['column_map']['id']);
    }

    #[Test]
    public function test_refuses_to_store_a_mapping_before_validation_passes(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV);

        $this->putJson(route('sheetflow.runs.mapping', ['run' => $run['id']]), [
            'column_map' => $run['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertStatus(409);

        $failed = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,nope
        CSV);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $failed['id']]))->assertOk();

        $this->putJson(route('sheetflow.runs.mapping', ['run' => $failed['id']]), [
            'column_map' => $failed['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertStatus(409);

        $next = $this->uploadCsv(<<<'CSV'
        kod,name,price
        B-2,Other,3
        CSV);

        $this->assertSame([], $next['templates']);
    }

    #[Test]
    public function test_hides_a_mapping_template_from_another_user(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
        ]);
        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'secret',
        ]);
        $csv = <<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV;

        $saved = $this->actingAs($owner)->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertCreated()->json();

        $this->assertIsArray($saved);

        $this->actingAs($owner)->postJson(route('sheetflow.runs.validate', ['run' => $saved['id']]))->assertOk();

        $this->actingAs($owner)->putJson(route('sheetflow.runs.mapping', ['run' => $saved['id']]), [
            'column_map' => $saved['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertOk();

        $ownerAgain = $this->actingAs($owner)->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertCreated()->json();

        $otherRun = $this->actingAs($other)->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertCreated()->json();

        $this->assertIsArray($ownerAgain);
        $this->assertCount(1, $ownerAgain['templates']);
        $this->assertSame('Shop', $ownerAgain['templates'][0]['name']);

        $this->assertIsArray($otherRun);
        $this->assertSame([], $otherRun['templates']);
    }

    #[Test]
    public function test_matches_an_owner_whose_key_arrives_as_a_numeric_string(): void
    {
        $owner = new User;
        $owner->setRawAttributes(['id' => '4']);

        $run = new ImportRun;
        $run->user_id = 4;

        $this->assertTrue($run->ownedBy($owner));
    }

    #[Test]
    public function test_hides_a_run_from_a_different_user(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret',
        ]);
        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'secret',
        ]);

        $run = $this->actingAs($owner)->post(
            route('sheetflow.uploads.store', ['definition' => 'products']),
            ['file' => UploadedFile::fake()->createWithContent('products.csv', <<<'CSV'
            ID,name,price
            A,B,1
            CSV)],
        )->assertCreated()->json();

        $this->assertIsArray($run);

        $this->actingAs($owner)
            ->getJson(route('sheetflow.runs.show', ['run' => $run['id']]))
            ->assertOk();

        $this->actingAs($other)
            ->getJson(route('sheetflow.runs.show', ['run' => $run['id']]))
            ->assertForbidden();
    }

    #[Test]
    public function test_previews_rows_from_an_xls_workbook(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['ID', 'name', 'price', 'updated'],
            ['A-1', 'Widget', 9.5, Date::PHPToExcel(new DateTimeImmutable('2024-05-01 13:45:00', new DateTimeZone('UTC')))],
        ]);
        $sheet->getStyle('D2')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');

        $temporary = tempnam(sys_get_temp_dir(), 'sheetflow');
        $path = $temporary.'.xls';
        rename($temporary, $path);
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $file = new UploadedFile($path, 'products.xls', 'application/vnd.ms-excel', null, true);

        $run = $this->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => $file,
        ])->assertCreated()->json();

        $this->assertIsArray($run);
        $this->assertSame('xls', $run['format']);
        $this->assertSame(['ID', 'name', 'price', 'updated'], $run['headers']);
        $this->assertSame('Widget', $run['preview_rows'][0]['name']);
        $this->assertSame('2024-05-01 13:45:00', $run['preview_rows'][0]['updated']);
    }

    #[Test]
    public function test_rejects_validation_when_a_required_field_is_not_mapped(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        User ID,Name,Points
        5745,Ada,10
        CSV);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.id.0', 'Map a column to ID.');
    }

    #[Test]
    public function test_validates_the_column_map_sent_with_the_validation_request(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        User ID,Name,Points
        5745,Ada,10
        CSV);

        $this->assertNull($run['column_map']['id']);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]), [
            'column_map' => [
                'id' => 'User ID',
                'name' => 'Name',
                'price' => 'Points',
            ],
        ])->assertOk()->json();

        $this->assertIsArray($validated);
        $this->assertSame('User ID', $validated['column_map']['id']);
        $this->assertSame('validated', $validated['status']);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function test_names_the_uploaded_file_when_an_xlsx_archive_cannot_be_read(): void
    {
        $file = UploadedFile::fake()->createWithContent('points-export.xlsx', 'not-a-zip');

        $message = $this->postJson(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => $file,
        ])->assertUnprocessable()->json('errors.file.0');

        $this->assertIsString($message);
        $this->assertSame('The file "points-export.xlsx" could not be read: Not a zip archive.', $message);
        $this->assertStringNotContainsString('storage', $message);
    }

    #[Test]
    public function test_returns_422_when_the_upload_is_not_a_spreadsheet(): void
    {
        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $this->postJson(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => $file,
        ])->assertUnprocessable();
    }

    #[Test]
    public function test_cancels_a_validation_batch_before_the_chunks_run(): void
    {
        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,2
        B-2,Other,3
        CSV);

        config(['queue.default' => 'database']);

        $started = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $batchId = ImportRun::query()->find($run['id'])->batch_id;

        $this->assertIsArray($started);
        $this->assertSame('validating', $started['status']);
        $this->assertNotNull($batchId);

        $cancelled = $this->postJson(route('sheetflow.runs.cancel', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($cancelled);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertTrue(Bus::findBatch($batchId)?->cancelled());
        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function test_hides_saved_maps_when_the_setting_is_off(): void
    {
        config(['sheetflow.save_templates' => false]);

        $run = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV);

        $this->assertFalse($run['save_templates']);

        $this->putJson(route('sheetflow.runs.mapping', ['run' => $run['id']]), [
            'column_map' => $run['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertForbidden();

        $second = $this->uploadCsv(<<<'CSV'
        kod,name,price
        B-2,Other,3
        CSV);

        $this->assertSame([], $second['templates']);
    }

    #[Test]
    public function test_hides_saved_maps_when_the_definition_refuses_them(): void
    {
        Sheetflow::definition(key: 'products', definition: TemplatesDisabledImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV);

        $this->assertFalse($run['save_templates']);

        $this->putJson(route('sheetflow.runs.mapping', ['run' => $run['id']]), [
            'column_map' => $run['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertForbidden();

        $second = $this->uploadCsv(<<<'CSV'
        kod,name,price
        B-2,Other,3
        CSV);

        $this->assertSame([], $second['templates']);
    }

    #[Test]
    public function test_keeps_saved_maps_when_the_definition_allows_them(): void
    {
        config(['sheetflow.save_templates' => false]);
        Sheetflow::definition(key: 'products', definition: TemplatesEnabledImport::class);

        $first = $this->uploadCsv(<<<'CSV'
        kod,name,price
        A-1,Widget,2
        CSV);

        $this->assertTrue($first['save_templates']);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $first['id']]))->assertOk();

        $this->putJson(route('sheetflow.runs.mapping', ['run' => $first['id']]), [
            'column_map' => $first['column_map'],
            'save_template' => true,
            'template_name' => 'Shop',
        ])->assertOk();

        $second = $this->uploadCsv(<<<'CSV'
        kod,name,price
        B-2,Other,3
        CSV);

        $this->assertCount(1, $second['templates']);
        $this->assertSame('Shop', $second['templates'][0]['name']);
    }

    #[Test]
    public function test_rejects_rows_the_definition_hook_marks_invalid(): void
    {
        Sheetflow::definition(key: 'products', definition: CheckedProductImport::class);
        Product::query()->create([
            'id' => 'B-2',
            'name' => 'Stored',
            'price' => 1,
        ]);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,1
        A-1,Other,2
        B-2,Fresh,3
        C-3,Ok,4
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(3, $validated['failed_rows']);

        $failures = $this->getJson(route('sheetflow.runs.failures', ['run' => $run['id']]))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($failures);
        $messages = [];

        foreach ($failures as $failure) {
            $this->assertIsArray($failure);
            $messages[$failure['row_number']] = $failure['errors']['id'][0] ?? null;
        }

        $this->assertSame('This ID is repeated in the file.', $messages[2]);
        $this->assertSame('This ID is repeated in the file.', $messages[3]);
        $this->assertSame('This ID already exists.', $messages[4]);

        $imported = $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($imported);
        $this->assertSame(1, $imported['imported_rows']);
        $this->assertTrue(Product::query()->where('id', 'C-3')->exists());
        $this->assertSame('Stored', Product::query()->where('id', 'B-2')->first()->name);
    }

    #[Test]
    public function test_rejects_every_row_that_repeats_a_value_marked_unique_in_the_file(): void
    {
        Sheetflow::definition(key: 'products', definition: UniqueProductImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,One,1
        A-1,Two,2
        B-2,Three,3
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(2, $validated['failed_rows']);

        $failures = $this->getJson(route('sheetflow.runs.failures', ['run' => $run['id']]))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($failures);
        $this->assertSame('The value in ID is repeated in the file.', $failures[0]['errors']['id'][0]);
        $this->assertSame('The value in ID is repeated in the file.', $failures[1]['errors']['id'][0]);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertTrue(Product::query()->where('id', 'B-2')->exists());
        $this->assertFalse(Product::query()->where('id', 'A-1')->exists());
    }

    #[Test]
    public function test_rejects_a_repeated_value_when_that_field_would_otherwise_be_emptied(): void
    {
        Sheetflow::definition(key: 'products', definition: UniqueNullablePriceImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,One,nope
        A-2,Two,nope
        B-3,Three,4
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(2, $validated['failed_rows']);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertSame(1, Product::query()->count());
        $this->assertEquals(4, Product::query()->where('id', 'B-3')->first()->price);
    }

    #[Test]
    public function test_stores_an_empty_value_when_the_field_allows_null_on_error(): void
    {
        Sheetflow::definition(key: 'products', definition: NullablePriceImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,nope
        CSV);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))->assertOk();
        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $product = Product::query()->where('id', 'A-1')->first();

        $this->assertNotNull($product);
        $this->assertNull($product->price);
    }

    #[Test]
    public function test_keeps_rejecting_a_required_field_when_null_on_error_is_declared(): void
    {
        Sheetflow::definition(key: 'products', definition: RequiredNullImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        ,Widget,1
        B-2,Other,2
        CSV);
        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['failed_rows']);
        $this->assertSame(['skip_row'], $validated['fields'][0]['allowed_invalid_actions']);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertFalse(Product::query()->where('name', 'Widget')->exists());
        $this->assertTrue(Product::query()->where('id', 'B-2')->exists());
    }

    #[Test]
    public function test_uses_the_error_action_chosen_for_the_column(): void
    {
        Sheetflow::definition(key: 'products', definition: ChoicePriceImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,nope
        CSV);

        $this->assertSame(['skip_row', 'null'], $run['fields'][2]['allowed_invalid_actions']);

        $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]), [
            'column_map' => $run['column_map'],
            'invalid_actions' => ['price' => 'stop'],
        ])->assertUnprocessable();

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]), [
            'column_map' => $run['column_map'],
            'invalid_actions' => ['price' => 'null'],
        ])->assertOk()->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame('null', $validated['invalid_actions']['price']);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertNull(Product::query()->where('id', 'A-1')->first()->price);
    }

    #[Test]
    public function test_imports_nothing_when_a_field_error_stops_the_import(): void
    {
        Sheetflow::definition(key: 'products', definition: StopPriceImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,Widget,nope
        B-2,Other,3
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(2, $validated['stopped_row']);
        $this->assertSame(1, $validated['valid_rows']);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertUnprocessable();

        $this->assertSame(0, Product::query()->count());
    }

    #[Test]
    public function test_rejects_rows_when_the_file_check_finds_a_group_over_the_limit(): void
    {
        SumByIdImport::$fileChecks = 0;
        Sheetflow::definition(key: 'products', definition: SumByIdImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,One,150
        A-1,Two,60
        B-2,Three,50
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, SumByIdImport::$fileChecks);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(2, $validated['failed_rows']);

        $failures = $this->getJson(route('sheetflow.runs.failures', ['run' => $run['id']]))
            ->assertOk()
            ->json('data');

        $this->assertIsArray($failures);
        $this->assertSame('The sum for this ID is over 200.', $failures[0]['errors']['price'][0]);
        $this->assertSame('The sum for this ID is over 200.', $failures[1]['errors']['price'][0]);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertSame(1, Product::query()->count());
        $this->assertEquals(50, Product::query()->where('id', 'B-2')->first()->price);
    }

    #[Test]
    public function test_rejects_a_row_from_a_column_value_in_the_file_check(): void
    {
        Sheetflow::definition(key: 'products', definition: ColumnLimitImport::class);

        $run = $this->uploadCsv(<<<'CSV'
        ID,name,price
        A-1,One,150
        B-2,Two,40
        CSV);

        $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
            ->assertOk()
            ->json();

        $this->assertIsArray($validated);
        $this->assertSame(1, $validated['valid_rows']);
        $this->assertSame(1, $validated['failed_rows']);

        $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

        $this->assertFalse(Product::query()->where('id', 'A-1')->exists());
        $this->assertTrue(Product::query()->where('id', 'B-2')->exists());
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadCsv(string $contents): array
    {
        $file = UploadedFile::fake()->createWithContent('products.csv', $contents);
        $payload = $this->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => $file,
        ])->assertCreated()->json();

        $this->assertIsArray($payload);

        return $payload;
    }
}
