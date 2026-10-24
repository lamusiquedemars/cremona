<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Invoices\InvoiceResource;

class CreateInvoice extends BusinessCreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
