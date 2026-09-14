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
            EditAction::make()->label(__('common.edit_company')),
            Action::make('archive')
                ->label(__('common.archive'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->authorize('update')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading(__('common.archive_company_heading'))
                ->modalDescription(__('common.archive_company_description'))
                ->action(fn () => app(CrmRecordManager::class)->archive($this->record)),
            Action::make('reactivate')
                ->label(__('common.reactivate'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->authorize('reactivate')
                ->visible(fn (): bool => $this->record->status === 'archived')
                ->action(fn () => app(CrmRecordManager::class)->reactivate($this->record)),
        ];
    }
}
