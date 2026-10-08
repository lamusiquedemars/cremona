<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Enums\RentalStatus;
use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Rentals\RentalResource;
use App\Models\RentalDocument;
use App\Services\RentalContractGenerator;
use App\Services\RentalManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use LogicException;

class EditRental extends BusinessEditRecord
{
    protected static string $resource = RentalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRentalContract')
                ->label('Générer le contrat de location')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->requiresConfirmation()
                ->modalDescription('Le PDF sera archivé dans l’espace privé. Si un contrat existe déjà, cette action créera une nouvelle version sans modifier la précédente.')
                ->action(function (RentalContractGenerator $generator): void {
                    try {
                        $generator->generate($this->record, RentalDocumentType::RentalContract, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Contrat non généré')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    $this->refreshFormData(['insurance_monthly_amount']);
                    Notification::make()->title('Contrat de location généré')->body('Le PDF immuable est archivé dans les documents privés de cette location.')->success()->send();
                }),
            Action::make('generateInsuranceContract')
                ->label('Générer le contrat d’assurance')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->visible(fn (): bool => (float) $this->record->insurance_monthly_amount > 0)
                ->requiresConfirmation()
                ->modalDescription('Le PDF sera archivé dans l’espace privé. Si un contrat existe déjà, cette action créera une nouvelle version sans modifier la précédente.')
                ->action(function (RentalContractGenerator $generator): void {
                    try {
                        $generator->generate($this->record, RentalDocumentType::InsuranceContract, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Contrat d’assurance non généré')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Contrat d’assurance généré')->body('Le PDF immuable est archivé dans les documents privés de cette location.')->success()->send();
                }),
            Action::make('downloadRentalContract')
                ->label('Télécharger le contrat de location')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->latestDocument(RentalDocumentType::RentalContract) !== null)
                ->url(function (): string {
                    $document = $this->latestDocument(RentalDocumentType::RentalContract);

                    return $document?->privateDocument === null ? '#' : route('private-documents.download', $document->privateDocument->public_id);
                })
                ->openUrlInNewTab(),
            Action::make('downloadInsuranceContract')
                ->label('Télécharger le contrat d’assurance')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->latestDocument(RentalDocumentType::InsuranceContract) !== null)
                ->url(function (): string {
                    $document = $this->latestDocument(RentalDocumentType::InsuranceContract);

                    return $document?->privateDocument === null ? '#' : route('private-documents.download', $document->privateDocument->public_id);
                })
                ->openUrlInNewTab(),
            Action::make('activate')
                ->label('Démarrer la location')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->visible(fn (): bool => $this->record->status === RentalStatus::Draft)
                ->requiresConfirmation()
                ->action(function (RentalManager $manager): void {
                    try {
                        $manager->activate($this->record);
                    } catch (LogicException $exception) {
                        Notification::make()->title('Location non démarrée')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Location démarrée')->body('L’instrument est maintenant indiqué comme en location.')->success()->send();
                }),
            Action::make('return')
                ->label('Enregistrer le retour')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (): bool => $this->record->status === RentalStatus::Active)
                ->schema([
                    DatePicker::make('returned_on')->label('Date de restitution')->default(today())->native(false)->required(),
                    Textarea::make('return_notes')->label('Constat ou observations')->rows(3),
                ])
                ->action(function (array $data, RentalManager $manager): void {
                    try {
                        $manager->return($this->record, Carbon::parse($data['returned_on']), $data['return_notes'] ?? null, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Retour non enregistré')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Restitution enregistrée')->body('L’instrument redevient disponible. Le constat est conservé avec la location.')->success()->send();
                }),
        ];
    }

    private function latestDocument(RentalDocumentType $type): ?RentalDocument
    {
        return RentalDocument::query()
            ->with('privateDocument')
            ->where('rental_id', $this->record->id)
            ->where('type', $type)
            ->where('status', RentalDocumentStatus::Generated)
            ->latest('version_number')
            ->first();
    }
}
