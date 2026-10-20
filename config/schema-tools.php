<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Paths
    |--------------------------------------------------------------------------
    |
    | Default paths for generated files.
    |
    */
    'export_paths' => [
        'migrations' => 'database/migrations',
        'models' => 'app/Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inference
    |--------------------------------------------------------------------------
    |
    | string_columns are always varchar. decimal_columns are always DECIMAL
    | sized from the observed integer digits and scale. varchar_default is the
    | minimum length emitted for varchar columns. A published copy of this file
    | is config/schema-tools-tools.php; those values override these defaults.
    |
    */
    'string_columns' => [],
    'decimal_columns' => [],
    'varchar_default' => 255,
];
