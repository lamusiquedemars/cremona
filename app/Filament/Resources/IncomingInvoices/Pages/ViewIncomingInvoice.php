<?php

namespace App\Filament\Resources\IncomingInvoices\Pages;

use App\Filament\Resources\IncomingInvoices\IncomingInvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewIncomingInvoice extends ViewRecord
{
    protected static string $resource = IncomingInvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->reference ?: 'Facture reçue #'.$this->record->remote_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadOriginal')
                ->label('Télécharger l’original')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->record->originalPrivateDocument !== null)
                ->url(fn (): string => route('private-documents.download', $this->record->originalPrivateDocument?->public_id))
                ->openUrlInNewTab(),
        ];
    }
}
