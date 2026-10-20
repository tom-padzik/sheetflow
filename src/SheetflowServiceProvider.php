<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Illuminate\Support\ServiceProvider;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Readers\OpenSpoutSpreadsheetReader;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;

class SheetflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sheetflow.php', 'sheetflow');
        $this->app->singleton(DefinitionRegistry::class);
        $this->app->singleton(SpreadsheetReaderInterface::class, OpenSpoutSpreadsheetReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sheetflow');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sheetflow.php' => config_path('sheetflow.php'),
            ], 'sheetflow-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sheetflow-migrations');
        }
    }
}
