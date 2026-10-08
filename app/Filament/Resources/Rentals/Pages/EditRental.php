<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Enums\RentalStatus;
use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\Rentals\RentalResource;
use App\Models\RentalDocument;
use App\Services\RentalAcceptanceManager;
use App\Services\RentalContractGenerator;
use App\Services\RentalManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            Action::make('sendAcceptance')
                ->label('Envoyer pour acceptation')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Le client recevra un lien personnel valable quatorze jours. Les contrats générés et actifs seront joints à cette demande.')
                ->action(function (RentalAcceptanceManager $manager): void {
                    try {
                        $request = $manager->issue($this->record, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Demande non envoyée')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Demande envoyée')->body('Un lien personnel a été envoyé à '.$request->recipient_email.'.')->success()->send();
                }),
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
            Action::make('generateReturnCertificate')
                ->label('Générer l’attestation de restitution')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->visible(fn (): bool => $this->record->status === RentalStatus::Returned)
                ->requiresConfirmation()
                ->action(function (RentalContractGenerator $generator): void {
                    try {
                        $generator->generate($this->record, RentalDocumentType::ReturnCertificate, auth()->user());
                    } catch (LogicException $exception) {
                        Notification::make()->title('Attestation non générée')->body($exception->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Attestation de restitution générée')->body('Le PDF immuable est archivé dans les documents privés de cette location.')->success()->send();
                }),
            Action::make('downloadReturnCertificate')
                ->label('Télécharger l’attestation de restitution')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->latestDocument(RentalDocumentType::ReturnCertificate) !== null)
                ->url(function (): string {
                    $document = $this->latestDocument(RentalDocumentType::ReturnCertificate);

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
                    Textarea::make('accessories_state')->label('Accessoires remis et état')->rows(3)->helperText('Ex. étui, archet, housse ; indiquez les éléments manquants ou endommagés.'),
                    Textarea::make('condition_notes')->label('État de l’instrument et observations')->rows(3),
                    TextInput::make('charge_amount')->label('Frais à régler')->numeric()->prefix('€')->default(0),
                    Textarea::make('charge_note')->label('Motif des frais')->rows(2),
                ])
                ->action(function (array $data, RentalManager $manager): void {
                    try {
                        $manager->return($this->record, Carbon::parse($data['returned_on']), $data['condition_notes'] ?? null, auth()->user(), $data);
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
