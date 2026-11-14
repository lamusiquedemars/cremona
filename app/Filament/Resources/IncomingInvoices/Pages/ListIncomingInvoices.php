<?php

namespace App\Filament\Resources\IncomingInvoices\Pages;

use App\Filament\Pages\BillingHome;
use App\Filament\Resources\IncomingInvoices\IncomingInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListIncomingInvoices extends ListRecords
{
    protected static string $resource = IncomingInvoiceResource::class;

    public function getBreadcrumbs(): array
    {
        return [BillingHome::getUrl() => 'Facturation', 'Factures reçues'];
    }
}
