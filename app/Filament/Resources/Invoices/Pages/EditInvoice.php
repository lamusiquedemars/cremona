<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Invoices\InvoiceResource;
use Illuminate\Contracts\Support\Htmlable;

class EditInvoice extends BusinessEditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        $heading = filled($this->record->reference)
            ? 'Facture '.$this->record->reference
            : 'Brouillon de facture';

        return filled($this->record->title) ? $heading.' — '.$this->record->title : $heading;
    }
}
