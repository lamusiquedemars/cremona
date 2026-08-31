<?php

namespace App\Filament\Resources\EmailMailboxes\Pages;

use App\Filament\Resources\EmailMailboxes\EmailMailboxResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailMailboxes extends ListRecords
{
    protected static string $resource = EmailMailboxResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Relier une boîte email')]; }
}
