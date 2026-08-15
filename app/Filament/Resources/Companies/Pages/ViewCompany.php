<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Services\CrmRecordManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Modifier l’entreprise'),
            Action::make('archive')
                ->label('Archiver')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->authorize('update')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading('Archiver cette entreprise ?')
                ->modalDescription('Ses demandes, notes et relations resteront conservées.')
                ->action(fn () => app(CrmRecordManager::class)->archive($this->record)),
            Action::make('reactivate')
                ->label('Réactiver')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->authorize('reactivate')
                ->visible(fn (): bool => $this->record->status === 'archived')
                ->action(fn () => app(CrmRecordManager::class)->reactivate($this->record)),
        ];
    }
}
