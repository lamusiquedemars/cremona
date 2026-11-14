<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkshopOrders extends ListRecords
{
    protected static string $resource = WorkshopOrderResource::class;
}
