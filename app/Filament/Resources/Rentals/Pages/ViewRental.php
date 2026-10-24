<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\Rentals\RentalResource;
use Filament\Actions\EditAction;
use Illuminate\Contracts\Support\Htmlable;

class ViewRental extends BusinessViewRecord
{
    protected static string $resource = RentalResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Location {$this->record->reference}";
    }

    public function getBreadcrumb(): string
    {
        return $this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier la location')];
    }
}
