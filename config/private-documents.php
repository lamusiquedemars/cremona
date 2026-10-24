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
        filled(env('SCW_PRIVATE_DOCUMENTS_BUCKET')) ? 'scaleway-private' : 'local',
    ),

    'allowed_disks' => array_values(array_unique([
        'local',
        'scaleway-private',
        // Lecture des documents créés pendant le déploiement initial, avant
        // le nom définitif du disque. La commande de migration les bascule.
        'scaleway_private',
        env('PRIVATE_DOCUMENTS_DISK', 'local'),
    ])),
];
