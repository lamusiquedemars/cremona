<?php

namespace App\Filament\Resources\PrivateDocuments\Pages;

use App\Filament\Resources\PrivateDocuments\PrivateDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListPrivateDocuments extends ListRecords
{
    protected static string $resource = PrivateDocumentResource::class;
}
