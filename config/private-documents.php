<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stockage des documents métier
    |--------------------------------------------------------------------------
    |
    | Les documents privés restent locaux par défaut. La bascule vers le
    | disque Scaleway est explicite et ne déplace jamais les documents déjà
    | déposés, qui conservent leur disque historique en base.
    |
    */
    'disk' => env('PRIVATE_DOCUMENTS_DISK', 'local'),
];
