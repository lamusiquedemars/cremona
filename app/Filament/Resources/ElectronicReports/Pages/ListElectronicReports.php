<?php

namespace App\Filament\Resources\ElectronicReports\Pages;

use App\Filament\Pages\BillingHome;
use App\Filament\Resources\ElectronicReports\ElectronicReportResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListElectronicReports extends ListRecords
{
    protected static string $resource = ElectronicReportResource::class;

    public function getSubheading(): ?string
    {
        return 'Les opérations sont préparées à partir des factures et encaissements. Aucune déclaration n’est envoyée automatiquement.';
    }

    public function getBreadcrumbs(): array
    {
        return [BillingHome::getUrl() => 'Facturation', 'E-reporting'];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes'),
            'to-review' => Tab::make('À contrôler')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', ['pending_review', 'needs_completion'])),
        ];
    }
}
