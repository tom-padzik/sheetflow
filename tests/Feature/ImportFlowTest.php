<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Tests\Fixtures\CustomProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\Product;
use Tpadzik\Sheetflow\Tests\Fixtures\ProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\User;

beforeEach(function (): void {
    Sheetflow::definition(key: 'products', definition: ProductImport::class);
    Sheetflow::definition(key: 'custom-products', definition: CustomProductImport::class);

    Schema::create('products', function ($table): void {
        $table->id();
        $table->string('sku')->unique();
        $table->string('name');
        $table->decimal('price', 8, 2)->nullable();
        $table->timestamps();
    });

    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
});

it('guesses columns and previews the uploaded file', function (): void {
    $run = uploadCsv("kod,name,price\nA-1,Widget,9.50\n");

    expect($run['column_map']['sku'])->toBe('kod')
        ->and($run['column_map']['name'])->toBe('name')
        ->and($run['status'])->toBe('staged')
        ->and($run['preview_rows'])->toHaveCount(1)
        ->and($run['total_rows'])->toBe(1);
});

it('detects a semicolon delimiter', function (): void {
    $run = uploadCsv("sku;name;price\nA-1;Widget;3\n");

    expect($run['delimiter'])->toBe(';')
        ->and($run['preview_rows'][0]['name'])->toBe('Widget');
});

it('rejects an unknown definition', function (): void {
    $file = UploadedFile::fake()->createWithContent('products.csv', "sku,name,price\nA,B,1\n");

    $this->post(route('sheetflow.uploads.store', ['definition' => 'missing']), [
        'file' => $file,
    ])->assertNotFound();
});

it('keeps invalid rows out of the database and imports the valid ones', function (): void {
    $run = uploadCsv("sku,name,price\nA-1,Widget,9.50\n,Missing,nope\n");

    $validated = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
        ->assertOk()
        ->json();

    expect($validated['status'])->toBe('validated')
        ->and($validated['valid_rows'])->toBe(1)
        ->and($validated['failed_rows'])->toBe(1)
        ->and(Product::query()->count())->toBe(0);

    $failures = $this->getJson(route('sheetflow.runs.failures', ['run' => $run['id']]))
        ->assertOk()
        ->json('data');

    expect($failures)->toHaveCount(1)
        ->and($failures[0]['row_number'])->toBe(3);

    $imported = $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))
        ->assertOk()
        ->json();

    expect($imported['status'])->toBe('completed')
        ->and($imported['imported_rows'])->toBe(1)
        ->and(Product::query()->where('sku', 'A-1')->exists())->toBeTrue();
});

it('updates an existing row when the match field is the same', function (): void {
    Product::query()->create([
        'sku' => 'A-1',
        'name' => 'Old',
        'price' => 1,
    ]);

    $run = uploadCsv("sku,name,price\nA-1,New,4\n");
    $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))->assertOk();
    $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

    expect(Product::query()->count())->toBe(1)
        ->and(Product::query()->first()->name)->toBe('New');
});

it('uses a custom persist method instead of the default upsert', function (): void {
    $file = UploadedFile::fake()->createWithContent('products.csv', "sku,name,price\nA-1,Widget,2\n");

    $run = $this->post(route('sheetflow.uploads.store', ['definition' => 'custom-products']), [
        'file' => $file,
    ])->assertCreated()->json();

    $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))->assertOk();
    $this->postJson(route('sheetflow.runs.import', ['run' => $run['id']]))->assertOk();

    expect(Product::query()->where('sku', 'custom-A-1')->exists())->toBeTrue();
});

it('stores a mapping template and offers it for the same headers', function (): void {
    $first = uploadCsv("kod,name,price\nA-1,Widget,2\n");

    $this->putJson(route('sheetflow.runs.mapping', ['run' => $first['id']]), [
        'column_map' => $first['column_map'],
        'save_template' => true,
        'template_name' => 'Shop',
    ])->assertOk();

    $second = uploadCsv("kod,name,price\nB-2,Other,3\n");

    expect($second['templates'])->toHaveCount(1)
        ->and($second['templates'][0]['name'])->toBe('Shop')
        ->and($second['templates'][0]['column_map']['sku'])->toBe('kod');
});

it('hides a run from a different user', function (): void {
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
        ['file' => UploadedFile::fake()->createWithContent('products.csv', "sku,name,price\nA,B,1\n")],
    )->assertCreated()->json();

    $this->actingAs($other)
        ->getJson(route('sheetflow.runs.show', ['run' => $run['id']]))
        ->assertForbidden();
});

it('cancels a validation batch before the chunks run', function (): void {
    $run = uploadCsv("sku,name,price\nA-1,Widget,2\nB-2,Other,3\n");

    config(['queue.default' => 'database']);

    $started = $this->postJson(route('sheetflow.runs.validate', ['run' => $run['id']]))
        ->assertOk()
        ->json();

    $batchId = ImportRun::query()->find($run['id'])->batch_id;

    expect($started['status'])->toBe('validating')
        ->and($batchId)->not->toBeNull();

    $cancelled = $this->postJson(route('sheetflow.runs.cancel', ['run' => $run['id']]))
        ->assertOk()
        ->json();

    expect($cancelled['status'])->toBe('cancelled')
        ->and(Bus::findBatch($batchId)?->cancelled())->toBeTrue()
        ->and(Product::query()->count())->toBe(0);
});

function uploadCsv(string $contents): array
{
    $file = UploadedFile::fake()->createWithContent('products.csv', $contents);

    return test()->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
        'file' => $file,
    ])->assertCreated()->json();
}
