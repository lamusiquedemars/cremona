<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateQuote extends BusinessCreateRecord
{
    protected static string $resource = QuoteResource::class;
}
