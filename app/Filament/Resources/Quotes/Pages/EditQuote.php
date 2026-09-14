<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Enums\QuoteStatus;
use App\Models\QuoteLineTemplate;
use App\Services\QuoteLineTemplateManager;
use App\Services\QuoteWorkflowManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markSent')
                ->label('Marquer comme envoyé')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Draft)
                ->schema([
                    Select::make('sent_via')->label('Mode d’envoi')->options([
                        'email' => 'Email',
                        'in_person' => 'Remis au client',
                        'other' => 'Autre canal',
                    ])->required(),
                ])
                ->action(function (array $data, QuoteWorkflowManager $manager): void {
                    try {
                        $manager->markSent($this->record, $data['sent_via']);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Devis non envoyé')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Devis marqué comme envoyé')->body('Le dossier atelier lié attend maintenant l’accord du client.')->success()->send();
                }),
            Action::make('markAccepted')
                ->label('Enregistrer l’accord client')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Sent)
                ->requiresConfirmation()
                ->action(function (QuoteWorkflowManager $manager): void {
                    try {
                        $manager->markAccepted($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Accord non enregistré')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('markDeclined')
                ->label('Enregistrer le refus')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === QuoteStatus::Sent)
                ->requiresConfirmation()
                ->action(function (QuoteWorkflowManager $manager): void {
                    try {
                        $manager->markDeclined($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Refus non enregistré')->body($exception->getMessage())->danger()->send();
                    }
                }),
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
