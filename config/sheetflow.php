<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | JSON routes live under "{prefix}/api". The Blade wizard lives at
    | "{prefix}/{definition}".
    |
    */

    'prefix' => env('SHEETFLOW_PREFIX', 'sheetflow'),

    'middleware' => ['web', 'auth'],

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
