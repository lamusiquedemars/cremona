<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Invoices\InvoiceResource;

class EditInvoice extends BusinessEditRecord
{
    protected static string $resource = InvoiceResource::class;
}
