<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Concurrency Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default concurrency driver that will be used
    | by the Concurrency facade. The "sync" driver runs tasks in-process and
    | sequentially, which is useful for testing. The "process" driver runs
    | each task in a separate PHP process so they execute concurrently.
    |
    | Supported: "sync", "process", "fork"
    |
    */

    'default' => env('CONCURRENCY_DRIVER', 'process'),

    /*
    |--------------------------------------------------------------------------
    | Task Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds a single concurrent task may run before
    | being terminated. The process driver runs each task in a subprocess
    | which, without an explicit timeout, inherits Symfony Process's 60 second
    | default. AI requests can take longer than that, so a larger timeout is
    | required to avoid killing in-flight model calls.
    |
    */

    'task_timeout' => env('CONCURRENCY_TASK_TIMEOUT', 360),
];
