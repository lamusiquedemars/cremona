<?php

namespace App\Filament\Resources\InstrumentAssets\Pages;

use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\InstrumentAssets\InstrumentAssetResource;
use Filament\Actions\EditAction;

class ViewInstrumentAsset extends BusinessViewRecord
{
    protected static string $resource = InstrumentAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier l’instrument')];
    }
}
