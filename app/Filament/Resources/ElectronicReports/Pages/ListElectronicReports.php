<?php

namespace App\Filament\Resources\ElectronicReports\Pages;

use App\Filament\Resources\ElectronicReports\ElectronicReportResource;
use Filament\Resources\Pages\ListRecords;

class ListElectronicReports extends ListRecords
{
    protected static string $resource = ElectronicReportResource::class;

    public function getSubheading(): ?string
    {
        return 'Les opérations sont préparées à partir des factures et encaissements. Aucune déclaration n’est envoyée automatiquement.';
    }
}
