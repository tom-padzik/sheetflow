<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | JSON routes live under "{prefix}/api". The Blade wizard lives at
    | "{prefix}/{definition}" when register_wizard is true.
    |
    | Set register_routes to false and call Sheetflow::routes() to mount that
    | group yourself, more than once if needed. wizards maps your own URIs to
    | definition keys. A value may be the key, or an array with definition,
    | name, and middleware.
    |
    */

    'prefix' => env('SHEETFLOW_PREFIX', 'sheetflow'),

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Manual testing
    |--------------------------------------------------------------------------
    |
    | SHEETFLOW_ALLOW_ALL lets every visitor open every import. The package
    | ignores this switch when the application environment is production.
    |
    */

    'allow_all' => env('SHEETFLOW_ALLOW_ALL', false),

    /*
    |--------------------------------------------------------------------------
    | Saved maps
    |--------------------------------------------------------------------------
    |
    | SHEETFLOW_SAVE_TEMPLATES shows the template name, the save button, and
    | previously saved maps. A definition can override this with saveTemplates().
    |
    */

    'save_templates' => filter_var(env('SHEETFLOW_SAVE_TEMPLATES', true), FILTER_VALIDATE_BOOLEAN),

    'register_routes' => true,

    'register_wizard' => true,

    'wizards' => [
        // 'imports/products' => 'products',
        // 'imports/customers' => [
        //     'definition' => 'customers',
        //     'name' => 'imports.customers',
        //     'middleware' => ['web', 'auth'],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The disk must support local paths. OpenSpout reads from the filesystem.
    |
    */

    'disk' => env('SHEETFLOW_DISK', 'local'),

    'directory' => 'sheetflow',

    'preview_rows' => 20,

    'chunk_size' => 100,

    'stage_batch_size' => 500,

    'max_rows' => 100000,

    'max_kilobytes' => 20480,

    'queue' => env('SHEETFLOW_QUEUE'),

    'connection' => env('SHEETFLOW_QUEUE_CONNECTION'),

];
