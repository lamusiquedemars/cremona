<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateStockItem extends BusinessCreateRecord
{
    protected static string $resource = StockItemResource::class;
}
