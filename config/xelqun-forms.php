<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The internal endpoints used by live fields, uploads and async selects.
    |
    */

    'routes' => [
        'prefix' => '_xelqun/forms',
        'middleware' => ['web'],
        'throttle' => [
            'update' => '240,1',
            'upload' => '30,1',
            'search' => '120,1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Tokens
    |--------------------------------------------------------------------------
    |
    | Minutes a form token stays valid. Set to 0 to never expire.
    |
    */

    'token_ttl' => 720,

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Files are stored on the temporary disk as soon as they are picked and
    | moved to the field's disk when the form is saved. Sizes are in KB and
    | the temporary lifetime is in minutes.
    |
    */

    'uploads' => [
        'disk' => 'public',
        'temporary_disk' => 'local',
        'temporary_directory' => 'xelqun-tmp',
        'temporary_ttl' => 1440,
        'max_size' => 10240,
    ],

];
