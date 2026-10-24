<?php

namespace App\Filament\Resources\WorkshopOrders\Pages;

use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use Filament\Actions\EditAction;
use Illuminate\Contracts\Support\Htmlable;

class ViewWorkshopOrder extends BusinessViewRecord
{
    protected static string $resource = WorkshopOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Dossier {$this->record->reference} — {$this->record->title}";
    }

    public function getBreadcrumb(): string
    {
        return $this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier le dossier'),
        ];
    }
}
