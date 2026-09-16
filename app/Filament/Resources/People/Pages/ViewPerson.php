<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Services\CrmRecordManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Filament\Pages\BusinessViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPerson extends BusinessViewRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label(__('cremona.person.edit_contact')),
            Action::make('archive')
                ->label(__('cremona.person.archive'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->authorize('update')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading(__('cremona.person.archive_contact'))
                ->modalDescription(__('cremona.person.archive_description'))
                ->action(fn () => app(CrmRecordManager::class)->archive($this->record)),
            Action::make('reactivate')
                ->label(__('cremona.person.reactivate'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->authorize('reactivate')
                ->visible(fn (): bool => $this->record->status === 'archived')
                ->action(fn () => app(CrmRecordManager::class)->reactivate($this->record)),
        ];
    }
}
