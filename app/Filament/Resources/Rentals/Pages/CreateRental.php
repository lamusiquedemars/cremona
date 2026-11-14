<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Rentals\RentalResource;

class CreateRental extends BusinessCreateRecord
{
    protected static string $resource = RentalResource::class;

    public function getTitle(): string
    {
        return 'Nouvelle location';
    }
}
