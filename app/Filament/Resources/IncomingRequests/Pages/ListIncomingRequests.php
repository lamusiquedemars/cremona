<?php

namespace App\Filament\Resources\IncomingRequests\Pages;

use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListIncomingRequests extends ListRecords
{
    protected static string $resource = IncomingRequestResource::class;
}
