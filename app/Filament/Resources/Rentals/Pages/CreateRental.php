<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Pages\BusinessCreateRecord;

class CreateRental extends BusinessCreateRecord
{
    protected static string $resource = RentalResource::class;
}
