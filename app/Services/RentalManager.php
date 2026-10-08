<?php

namespace App\Services;

use App\Enums\InstrumentAssetStatus;
use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalStatus;
use App\Models\InstrumentAsset;
use App\Models\Rental;
use App\Models\RentalReturn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class RentalManager
{
    public function activate(Rental $rental, bool $acceptanceOverride = false, ?User $actor = null): Rental
    {
        return DB::transaction(function () use ($rental, $acceptanceOverride, $actor): Rental {
            $rental = Rental::query()->lockForUpdate()->with('latestAcceptance')->findOrFail($rental->getKey());
            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);

            if (! $instrument->available_for_rental || $instrument->status !== InstrumentAssetStatus::Available) {
                throw new LogicException("L’instrument « {$instrument->name} » n’est pas disponible pour cette location.");
            }
            if ($rental->latestAcceptance !== null
                && $rental->latestAcceptance->status !== RentalAcceptanceStatus::Accepted
                && ! $acceptanceOverride) {
                throw new LogicException('Le client n’a pas encore accepté la demande. Confirmez explicitement la remise physique pour poursuivre.');
            }

            $instrument->update(['status' => InstrumentAssetStatus::Rented]);
            $rental->update(['status' => RentalStatus::Active, 'starts_on' => $rental->starts_on ?? today()]);
            app(AuditLogger::class)->record('rental.started', $rental, $actor, [
                'acceptance_status' => $rental->latestAcceptance?->status?->value,
                'acceptance_override' => $rental->latestAcceptance !== null && $rental->latestAcceptance->status !== RentalAcceptanceStatus::Accepted,
            ]);

            return $rental->fresh();
        });
    }

    /** @param array<string, mixed> $details */
    public function return(Rental $rental, Carbon $returnedOn, ?string $notes, ?User $actor = null, array $details = []): Rental
    {
        return DB::transaction(function () use ($rental, $returnedOn, $notes, $actor, $details): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->getKey());
            if ($rental->status !== RentalStatus::Active) {
                throw new LogicException('Seule une location en cours peut être restituée.');
            }

            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);
            $instrument->update(['status' => InstrumentAssetStatus::Available]);
            $rental->update([
                'status' => RentalStatus::Returned,
                'returned_on' => $returnedOn->toDateString(),
                'returned_at' => now(),
                'returned_by_user_id' => $actor?->getKey(),
                'return_notes' => filled($notes) ? trim($notes) : null,
            ]);
            RentalReturn::query()->create([
                'rental_id' => $rental->id,
                'returned_on' => $returnedOn->toDateString(),
                'accessories_state' => filled($details['accessories_state'] ?? null) ? trim((string) $details['accessories_state']) : null,
                'condition_notes' => filled($details['condition_notes'] ?? null) ? trim((string) $details['condition_notes']) : null,
                'charge_amount' => (float) ($details['charge_amount'] ?? 0),
                'charge_note' => filled($details['charge_note'] ?? null) ? trim((string) $details['charge_note']) : null,
                'recorded_by_user_id' => $actor?->id,
                'recorded_at' => now(),
            ]);

            app(AuditLogger::class)->record('rental.returned', $rental, $actor, [
                'returned_on' => $rental->returned_on?->toDateString(),
            ]);

            return $rental->fresh();
        });
    }
}
