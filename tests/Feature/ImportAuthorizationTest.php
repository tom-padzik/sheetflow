<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Tests\Fixtures\AllowProducts;
use Tpadzik\Sheetflow\Tests\Fixtures\ClosedProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\OpenProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\ProductImport;
use Tpadzik\Sheetflow\Tests\TestCase;

class ImportAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sheetflow::definition(key: 'products', definition: ProductImport::class);
        Sheetflow::definition(key: 'customers', definition: ProductImport::class);
    }

    #[Test]
    public function test_opens_an_import_when_no_authorization_rule_is_registered(): void
    {
        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk();
    }

    #[Test]
    public function test_forbids_an_import_the_global_rule_refuses(): void
    {
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return 'products' === $definition;
        });

        $this->get(route('sheetflow.wizard', ['definition' => 'customers']))
            ->assertForbidden();

        $this->post(route('sheetflow.uploads.store', ['definition' => 'customers']), [
            'file' => UploadedFile::fake()->createWithContent('people.csv', <<<'CSV'
            ID,name,price
            A,B,1
            CSV),
        ])->assertForbidden();

        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk();
    }

    #[Test]
    public function test_accepts_an_invokable_class_as_the_authorization_rule(): void
    {
        Sheetflow::authorize(AllowProducts::class);

        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk();

        $this->get(route('sheetflow.wizard', ['definition' => 'customers']))
            ->assertForbidden();
    }

    #[Test]
    public function test_forbids_an_import_whose_definition_rule_returns_false(): void
    {
        Sheetflow::definition(key: 'closed', definition: ClosedProductImport::class);

        $this->get(route('sheetflow.wizard', ['definition' => 'closed']))
            ->assertForbidden();
    }

    #[Test]
    public function test_allows_an_import_when_the_definition_rule_returns_true(): void
    {
        Sheetflow::definition(key: 'open', definition: OpenProductImport::class);
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return false;
        });

        $this->get(route('sheetflow.wizard', ['definition' => 'open']))
            ->assertOk();
    }

    #[Test]
    public function test_allows_an_import_the_global_rule_accepts_when_the_definition_refuses_it(): void
    {
        Sheetflow::definition(key: 'closed', definition: ClosedProductImport::class);
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return true;
        });

        $this->get(route('sheetflow.wizard', ['definition' => 'closed']))
            ->assertOk();
    }

    #[Test]
    #[DataProvider('forbiddenRunActions')]
    public function test_returns_403_for_a_run_when_the_definition_is_forbidden(string $method, string $name): void
    {
        $run = $this->post(route('sheetflow.uploads.store', ['definition' => 'products']), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', <<<'CSV'
            ID,name,price
            A,B,1
            CSV),
        ])->assertCreated()->json();

        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return false;
        });

        $this->assertIsArray($run);

        $this->{$method}(route($name, ['run' => $run['id']]))
            ->assertForbidden();
    }

    #[Test]
    public function test_still_serves_wizard_assets_when_the_import_is_forbidden(): void
    {
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return false;
        });

        $this->get(route('sheetflow.assets.wizard'))
            ->assertOk();
    }

    #[Test]
    public function test_lets_everyone_in_outside_production_when_allow_all_is_on(): void
    {
        config(['sheetflow.allow_all' => true]);
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return false;
        });

        $this->get(route('sheetflow.wizard', ['definition' => 'products']))
            ->assertOk();
    }

    #[Test]
    public function test_ignores_allow_all_when_the_environment_is_production(): void
    {
        $this->app['env'] = 'production';
        config(['sheetflow.allow_all' => true]);
        Sheetflow::authorize(function (?Authenticatable $user, string $definition): bool {
            return false;
        });
        $this->withoutExceptionHandling();

        try {
            $this->get(route('sheetflow.wizard', ['definition' => 'products']));
            $this->fail('The production switch should refuse the import.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function forbiddenRunActions(): array
    {
        return [
            'show' => ['getJson', 'sheetflow.runs.show'],
            'validation' => ['postJson', 'sheetflow.runs.validate'],
            'import' => ['postJson', 'sheetflow.runs.import'],
        ];
    }
}
