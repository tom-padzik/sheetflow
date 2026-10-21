<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Illuminate\Support\ServiceProvider;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Readers\SpreadsheetReader;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;
use Tpadzik\Sheetflow\Support\ImportGate;
use Tpadzik\Sheetflow\Support\UiPaths;

class SheetflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sheetflow.php', 'sheetflow');
        $this->app->singleton(DefinitionRegistry::class);
        $this->app->singleton(ImportGate::class);
        $this->app->singleton(SpreadsheetReaderInterface::class, SpreadsheetReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sheetflow');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'sheetflow');

        if ($this->app['config']->get('sheetflow.register_routes', true)) {
            Sheetflow::routes();
        }

        Sheetflow::registerConfiguredWizards();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sheetflow.php' => config_path('sheetflow.php'),
            ], 'sheetflow-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sheetflow-migrations');

            $this->publishes(UiPaths::publishable(), 'sheetflow-ui');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/sheetflow'),
            ], 'sheetflow-translations');
        }
    }
}
