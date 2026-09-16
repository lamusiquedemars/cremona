<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateCampaign extends BusinessCreateRecord
{
    protected static string $resource = CampaignResource::class;
}
