<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\QuoteLineTemplate;
use App\Services\QuoteLineTemplateManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addTemplateLine')
                ->label('Ajouter un modèle')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    Select::make('template_id')
                        ->label('Modèle de ligne')
                        ->options(fn (): array => QuoteLineTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('label')
                            ->pluck('label', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')->label('Quantité')->numeric()->minValue(0.01),
                ])
                ->action(function (array $data, QuoteLineTemplateManager $manager): void {
                    $manager->addToQuote(
                        $this->record,
                        QuoteLineTemplate::query()->findOrFail($data['template_id']),
                        filled($data['quantity'] ?? null) ? (float) $data['quantity'] : null,
                    );
                    $this->record->refresh();
                }),
        ];
    }
}
