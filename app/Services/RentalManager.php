<?php

namespace App\Services;

use App\Enums\InstrumentAssetStatus;
use App\Enums\RentalStatus;
use App\Models\InstrumentAsset;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;
use LogicException;

class RentalManager
{
    public function activate(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->getKey());
            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);

            if (! $instrument->available_for_rental || $instrument->status !== InstrumentAssetStatus::Available) {
                throw new LogicException("L’instrument « {$instrument->name} » n’est pas disponible pour cette location.");
            }

            $instrument->update(['status' => InstrumentAssetStatus::Rented]);
            $rental->update(['status' => RentalStatus::Active, 'starts_on' => $rental->starts_on ?? today()]);

            return $rental->fresh();
        });
    }

    public function return(Rental $rental): Rental
    {
        return DB::transaction(function () use ($rental): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->getKey());
            if ($rental->status !== RentalStatus::Active) {
                throw new LogicException('Seule une location en cours peut être restituée.');
            }

            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);
            $instrument->update(['status' => InstrumentAssetStatus::Available]);
            $rental->update(['status' => RentalStatus::Returned, 'returned_on' => today()]);

            return $rental->fresh();
        });
    }
}
