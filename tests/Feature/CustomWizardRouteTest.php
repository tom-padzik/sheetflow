<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Tests\Fixtures\CustomProductImport;
use Tpadzik\Sheetflow\Tests\Fixtures\ProductImport;
use Tpadzik\Sheetflow\Tests\TestCase;

class CustomWizardRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sheetflow::definition(key: 'products', definition: ProductImport::class);
        Sheetflow::definition(key: 'customers', definition: CustomProductImport::class);
    }

    #[Test]
    public function test_renders_each_import_on_its_own_route(): void
    {
        Sheetflow::wizardRoute(uri: '/imports/products', definition: 'products', name: 'imports.products');
        Sheetflow::wizardRoute(uri: '/imports/customers', definition: 'customers', name: 'imports.customers');

        $this->get('/imports/products')
            ->assertOk()
            ->assertSee('data-definition="products"', false);

        $this->get(route('imports.customers'))
            ->assertOk()
            ->assertSee('data-definition="customers"', false);
    }

    #[Test]
    public function test_registers_the_wizard_map_from_configuration(): void
    {
        config([
            'sheetflow.wizards' => [
                'catalog/products' => 'products',
                'catalog/customers' => [
                    'definition' => 'customers',
                    'name' => 'catalog.customers',
                ],
            ],
        ]);

        Sheetflow::registerConfiguredWizards();

        $this->get('/catalog/products')
            ->assertOk()
            ->assertSee('data-definition="products"', false);

        $this->get(route('catalog.customers'))
            ->assertOk()
            ->assertSee('data-definition="customers"', false);
    }

    #[Test]
    public function test_returns_404_when_a_custom_route_names_an_unknown_definition(): void
    {
        Sheetflow::wizardRoute(uri: '/imports/missing', definition: 'missing');

        $this->get('/imports/missing')->assertNotFound();
    }

    #[Test]
    public function test_mounts_the_api_and_wizard_again_under_another_prefix(): void
    {
        Sheetflow::routes(
            prefix: 'catalog',
            middleware: ['web'],
            name: 'catalog.sheetflow.',
            wizard: true,
        );

        $this->get('/catalog/products')
            ->assertOk()
            ->assertSee('data-definition="products"', false);

        $this->get('/catalog/customers')
            ->assertOk()
            ->assertSee('data-definition="customers"', false);
    }

    #[Test]
    public function test_rejects_a_wizard_entry_that_has_no_definition(): void
    {
        config([
            'sheetflow.wizards' => [
                'imports/broken' => ['name' => 'imports.broken'],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        Sheetflow::registerConfiguredWizards();
    }
}
