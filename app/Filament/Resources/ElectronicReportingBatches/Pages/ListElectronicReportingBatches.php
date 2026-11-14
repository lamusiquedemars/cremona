<?php

namespace App\Filament\Resources\ElectronicReportingBatches\Pages;

use App\Filament\Pages\BillingHome;
use App\Filament\Resources\ElectronicReportingBatches\ElectronicReportingBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListElectronicReportingBatches extends ListRecords
{
    protected static string $resource = ElectronicReportingBatchResource::class;

    public function getSubheading(): ?string
    {
        return 'Lots et événements relevés en lecture seule depuis Super PDP.';
    }

    public function getBreadcrumbs(): array
    {
        return [BillingHome::getUrl() => 'Facturation', 'Suivi des transmissions'];
    }
}
