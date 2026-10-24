<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Invoices\InvoiceResource;

class CreateInvoice extends BusinessCreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return 'Nouvelle facture';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouvelle facture';
    }
}
