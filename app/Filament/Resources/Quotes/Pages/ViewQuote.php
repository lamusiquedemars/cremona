<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected string $view = 'filament.resources.quotes.pages.view-quote';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string|Htmlable
    {
        return "Devis {$this->record->reference} — {$this->record->title}";
    }

    public function getBreadcrumb(): string
    {
        return $this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier le devis')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Draft),
            DeleteAction::make()->label('Supprimer le brouillon')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Draft),
        ];
    }

    /** @return array<string, Quote> */
    protected function getViewData(): array
    {
        return [
            'quote' => $this->record->loadMissing([
                'organization',
                'person.contactMethods',
                'company.contactMethods',
                'lines',
            ]),
        ];
    }
}
