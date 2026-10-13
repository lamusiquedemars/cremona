<?php

namespace App\Services;

use App\Enums\InstrumentAssetStatus;
use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalStatus;
use App\Models\InstrumentAsset;
use App\Models\Rental;
use App\Models\RentalReturn;
use App\Models\User;
use App\Mail\RentalHandoverConfirmation;
use App\Mail\RentalReturnConfirmation;
use App\Enums\ContactMethodType;
use App\Models\ContactMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class RentalManager
{
    public function activate(Rental $rental, ?User $actor = null): Rental
    {
        $started = DB::transaction(function () use ($rental, $actor): Rental {
            $rental = Rental::query()->lockForUpdate()->with('latestAcceptance')->findOrFail($rental->getKey());
            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);

            if (! $instrument->available_for_rental || $instrument->status !== InstrumentAssetStatus::Available) {
                throw new LogicException("L’instrument « {$instrument->name} » n’est pas disponible pour cette location.");
            }
            if ($rental->status !== RentalStatus::Accepted || $rental->latestAcceptance?->status !== RentalAcceptanceStatus::Accepted) {
                throw new LogicException('Le dossier doit être accepté par le client avant de confirmer la remise de l’instrument.');
            }

            $instrument->update(['status' => InstrumentAssetStatus::Rented]);
            $rental->update(['status' => RentalStatus::HandedOver, 'starts_on' => $rental->starts_on ?? today(), 'collection_status' => 'to_set_up', 'collection_handled_at' => null]);
            app(AuditLogger::class)->record('rental.started', $rental, $actor, [
                'acceptance_status' => $rental->latestAcceptance?->status?->value,
            ]);

            return $rental->fresh();
        });

        $this->sendClientMessage($started, RentalHandoverConfirmation::class);

        return $started;
    }

    /** @param array<string, mixed> $details */
    public function return(Rental $rental, Carbon $returnedOn, ?string $notes, ?User $actor = null, array $details = []): Rental
    {
        $returned = DB::transaction(function () use ($rental, $returnedOn, $notes, $actor, $details): Rental {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->getKey());
            if ($rental->status !== RentalStatus::HandedOver) {
                throw new LogicException('Seule une location en cours peut être restituée.');
            }

            $instrument = InstrumentAsset::query()->lockForUpdate()->findOrFail($rental->instrument_asset_id);
            $instrument->update(['status' => InstrumentAssetStatus::Available]);
            $rental->update([
                'status' => RentalStatus::Returned,
                'collection_status' => 'to_stop',
                'collection_handled_at' => null,
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

        $this->sendClientMessage($returned, RentalReturnConfirmation::class);

        return $returned;
    }

    /** @param class-string<\Illuminate\Mail\Mailable> $mailable */
    private function sendClientMessage(Rental $rental, string $mailable): void
    {
        $email = ContactMethod::query()->where('contactable_type', $rental->person?->getMorphClass())
            ->where('contactable_id', $rental->person_id)->where('type', ContactMethodType::Email)->orderByDesc('is_primary')->value('value');
        if (filled($email)) {
            \Illuminate\Support\Facades\Mail::to($email)->send(new $mailable($rental));
        }
    }
}
