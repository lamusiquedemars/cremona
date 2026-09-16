<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Pages\BusinessEditRecord;

class EditStockItem extends BusinessEditRecord
{
    protected static string $resource = StockItemResource::class;
}
