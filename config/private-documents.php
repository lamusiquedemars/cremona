<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Private document storage
    |--------------------------------------------------------------------------
    |
    | New documents use this disk. Historic documents keep their recorded disk
    | so a controlled migration can be performed without breaking downloads.
    |
    */
    'disk' => env(
        'PRIVATE_DOCUMENTS_DISK',
        filled(env('SCW_PRIVATE_DOCUMENTS_BUCKET')) ? 's3' : 'local',
    ),

    'allowed_disks' => array_values(array_unique([
        'local',
        env('PRIVATE_DOCUMENTS_DISK', 'local'),
    ])),
];
