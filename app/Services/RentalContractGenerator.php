<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Models\ContactMethod;
use App\Models\PrivateDocument;
use App\Models\Rental;
use App\Models\RentalDocument;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class RentalContractGenerator
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly QuoteDocumentProfileManager $profiles,
        private readonly RentalContractRenderer $renderer,
        private readonly RentalDocumentManager $documents,
    ) {}

    public function generate(Rental $rental, RentalDocumentType $type, ?User $actor = null): RentalDocument
    {
        return $this->context->run($rental->organization, fn (): RentalDocument => $this->generateForOrganization($rental, $type, $actor));
    }

    private function generateForOrganization(Rental $rental, RentalDocumentType $type, ?User $actor): RentalDocument
    {
        if (! in_array($type, [RentalDocumentType::RentalContract, RentalDocumentType::InsuranceContract, RentalDocumentType::ReturnCertificate], true)) {
            throw new LogicException('Ce type de document ne peut pas encore être généré.');
        }

        $rental = Rental::query()->with(['organization', 'instrument', 'person'])->findOrFail($rental->getKey());
        $snapshot = $this->snapshot($rental, $type);
        $content = $this->renderer->render($type, $snapshot);
        $publicId = (string) Str::ulid();
        $filename = (string) Str::uuid().'.pdf';
        $path = 'documents/'.$rental->organization_id.'/'.$publicId.'/'.$filename;

        Storage::disk('local')->put($path, $content);

        try {
            return DB::transaction(function () use ($rental, $type, $actor, $snapshot, $content, $publicId, $path): RentalDocument {
                $previous = RentalDocument::query()
                    ->where('rental_id', $rental->id)
                    ->where('type', $type)
                    ->where('status', RentalDocumentStatus::Generated)
                    ->latest('version_number')
                    ->lockForUpdate()
                    ->first();

                $privateDocument = new PrivateDocument([
                    'public_id' => $publicId,
                    'uploaded_by_user_id' => $actor?->id,
                    'version_of_id' => $previous?->private_document_id,
                    'version_number' => $previous === null ? 1 : $previous->version_number + 1,
                    'title' => $type->getLabel().' '.$rental->reference,
                    'category' => 'Location',
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $this->filename($rental, $type, $previous === null ? 1 : $previous->version_number + 1),
                    'declared_mime_type' => 'application/pdf',
                    'detected_mime_type' => 'application/pdf',
                    'size' => strlen($content),
                    'sha256' => hash('sha256', $content),
                ]);
                $privateDocument->organization_id = $rental->organization_id;
                $privateDocument->save();

                $document = $this->documents->registerGenerated(
                    $rental,
                    $privateDocument,
                    $type,
                    match ($type) {
                        RentalDocumentType::RentalContract => 'contempo-location-v1',
                        RentalDocumentType::InsuranceContract => 'contempo-assurance-v1',
                        RentalDocumentType::ReturnCertificate => 'contempo-restitution-v1',
                    },
                    $snapshot,
                    $actor,
                    $previous,
                );

                if ($previous !== null) {
                    $previous->update(['status' => RentalDocumentStatus::Superseded]);
                }

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Rental $rental, RentalDocumentType $type): array
    {
        $issuer = $this->profiles->assertIssuerIsReady($rental->organization)->snapshot();
        $person = $rental->person;
        $instrument = $rental->instrument;

        if ($person === null) {
            throw new LogicException('Sélectionnez le locataire avant de générer le contrat.');
        }
        if ($instrument === null) {
            throw new LogicException('Sélectionnez l’instrument avant de générer le contrat.');
        }

        $methods = ContactMethod::query()
            ->where('contactable_type', $person->getMorphClass())
            ->where('contactable_id', $person->id)
            ->get()
            ->groupBy(fn (ContactMethod $method): string => $method->type->value);
        $email = $methods->get(ContactMethodType::Email->value)?->sortByDesc('is_primary')->first()?->value;
        $phone = $methods->get(ContactMethodType::Phone->value)?->sortByDesc('is_primary')->first()?->value;

        $missing = collect([
            'Adresse' => $person->address_line_1,
            'Code postal' => $person->postal_code,
            'Ville' => $person->city,
            'Téléphone' => $phone,
            'E-mail' => $email,
            'Début prévu' => $rental->starts_on,
        ])->filter(fn (mixed $value): bool => blank($value))->keys()->all();
        if ($missing !== []) {
            throw new LogicException('Complétez la fiche du locataire et la location avant de générer le contrat : '.implode(', ', $missing).'.');
        }

        $insurance = (float) $rental->insurance_monthly_amount;
        if ($type === RentalDocumentType::InsuranceContract && ($insurance <= 0 || $rental->insurance_plan_id === null)) {
            throw new LogicException('Sélectionnez une formule d’assurance avant de générer le contrat d’assurance.');
        }
        $return = $rental->returnRecord;
        if ($type === RentalDocumentType::ReturnCertificate && $return === null) {
            throw new LogicException('Enregistrez d’abord la restitution physique avant de générer son attestation.');
        }

        return [
            'issuer' => $issuer,
            'rental' => [
                'reference' => $rental->reference,
                'starts_on' => $rental->starts_on?->format('d/m/Y'),
                'expected_return_on' => $rental->expected_return_on?->format('d/m/Y'),
                'monthly_amount' => number_format((float) $rental->unit_amount, 2, ',', ' '),
                'insurance_monthly_amount' => number_format($insurance, 2, ',', ' '),
                'total_monthly_amount' => number_format((float) $rental->unit_amount + $insurance, 2, ',', ' '),
                'insurance_plan_name' => $rental->insurance_plan_name,
                'insurance_clause_version' => $rental->insurance_clause_version,
                'insurance_coverage_summary' => $rental->insurance_coverage_summary,
            ],
            'client' => [
                'name' => $person->display_name,
                'address_line_1' => $person->address_line_1,
                'address_line_2' => $person->address_line_2,
                'postal_code' => $person->postal_code,
                'city' => $person->city,
                'country_code' => $person->country_code,
                'email' => $email,
                'phone' => $phone,
            ],
            'instrument' => [
                'reference' => $instrument->reference,
                'name' => $instrument->name,
            ],
            'return' => $return === null ? null : [
                'returned_on' => $return->returned_on?->format('d/m/Y'),
                'accessories_state' => $return->accessories_state,
                'condition_notes' => $return->condition_notes,
                'charge_amount' => number_format((float) $return->charge_amount, 2, ',', ' '),
                'charge_note' => $return->charge_note,
            ],
            'generated_on' => now()->format('d/m/Y'),
        ];
    }

    private function filename(Rental $rental, RentalDocumentType $type, int $version): string
    {
        $prefix = match ($type) {
            RentalDocumentType::RentalContract => 'contrat-location',
            RentalDocumentType::InsuranceContract => 'contrat-assurance',
            RentalDocumentType::ReturnCertificate => 'attestation-restitution',
        };

        return $prefix.'-'.Str::slug((string) $rental->reference).'-v'.$version.'.pdf';
    }
}
