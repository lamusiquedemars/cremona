<?php

namespace App\Filament\Resources\QuoteLineTemplates\Pages;

use App\Filament\Resources\QuoteLineTemplates\QuoteLineTemplateResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateQuoteLineTemplate extends BusinessCreateRecord
{
    protected static string $resource = QuoteLineTemplateResource::class;
}
