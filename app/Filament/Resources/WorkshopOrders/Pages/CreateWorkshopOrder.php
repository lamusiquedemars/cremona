<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateWorkshopOrder extends BusinessCreateRecord
{
    protected static string $resource = WorkshopOrderResource::class;
}
