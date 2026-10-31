<?php

namespace App\Filament\Resources\ElectronicReports\Pages;

use App\Filament\Resources\ElectronicReports\ElectronicReportResource;
use App\Services\ElectronicReportingManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use LogicException;
use Throwable;

class ViewElectronicReport extends ViewRecord
{
    protected static string $resource = ElectronicReportResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'E-reporting — '.($this->record->invoice?->reference ?: '#'.$this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewInSuperPdp')
                ->label('Prévisualiser dans Super PDP')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status, ['pending_review', 'previewed'], true))
                ->requiresConfirmation()
                ->modalDescription('Consulte les données agrégées par Super PDP pour la période concernée. Cette action ne crée ni ne transmet de déclaration.')
                ->action(function (ElectronicReportingManager $manager): void {
                    try {
                        $manager->preview($this->record);
                        $this->record->refresh();
                        Notification::make()->title('Aperçu Super PDP enregistré')->success()->send();
                    } catch (LogicException $exception) {
                        Notification::make()->title('Aperçu impossible')->body($exception->getMessage())->danger()->send();
                    } catch (Throwable) {
                        Notification::make()->title('Aperçu impossible')->body('Super PDP a renvoyé une erreur technique. Aucune déclaration n’a été créée.')->danger()->send();
                    }
                }),
        ];
    }
}
