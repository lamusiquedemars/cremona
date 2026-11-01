<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Pages\BillingHome;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getBreadcrumbs(): array
    {
        return [BillingHome::getUrl() => 'Facturation', 'Factures de vente'];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes'),
            'drafts' => Tab::make('Brouillons')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Draft)),
            'to-collect' => Tab::make('À encaisser')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])),
        ];
    }
}
