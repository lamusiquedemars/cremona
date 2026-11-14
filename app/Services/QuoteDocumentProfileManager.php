<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\Company;
use App\Models\ContactMethod;
use App\Models\Person;
use App\Models\Quote;
use LogicException;

class QuoteDocumentProfileManager
{
    /** @return array<int, string> */
    public function missingIssuerFields(Organization $organization): array
    {
        $profile = OrganizationLegalProfile::withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->first();
        if (! $profile instanceof OrganizationLegalProfile) {
            return ['Coordonnées et mentions légales'];
        }

        $labels = [
            'display_name' => 'Nom affiché',
            'legal_name' => 'Raison sociale ou nom complet',
            'email' => 'Adresse email',
            'address_line_1' => 'Adresse',
            'postal_code' => 'Code postal',
            'city' => 'Ville',
            'country_code' => 'Pays',
        ];
        $missing = collect($labels)
            ->filter(fn (string $label, string $field): bool => blank($profile->{$field}))
            ->values()
            ->all();

        if (mb_strtoupper((string) $profile->country_code) === 'FR' && blank($profile->registration_number)) {
            $missing[] = 'SIRET ou numéro d’immatriculation';
        }

        return $missing;
    }

    public function assertIssuerIsReady(Organization $organization): OrganizationLegalProfile
    {
        $missing = $this->missingIssuerFields($organization);
        if (count($missing)) {
            throw new LogicException('Complétez « Coordonnées et mentions légales » avant d’émettre ou d’exporter ce devis : '.implode(', ', $missing).'.');
        }

        return OrganizationLegalProfile::withoutGlobalScopes()
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();
    }

    /** @return array<string, string|null> */
    public function recipientSnapshot(Quote $quote): array
    {
        $recipient = $quote->company_id !== null
            ? Company::withoutGlobalScopes()->find($quote->company_id)
            : Person::withoutGlobalScopes()->find($quote->person_id);
        if ($recipient === null) {
            throw new LogicException('Renseignez un destinataire avant d’émettre ou d’exporter ce devis.');
        }
        $email = ContactMethod::withoutGlobalScopes()
            ->where('organization_id', $quote->organization_id)
            ->where('contactable_type', $recipient->getMorphClass())
            ->where('contactable_id', $recipient->getKey())
            ->where('type', 'email')
            ->orderByDesc('is_primary')
            ->value('value');

        return [
            'name' => $recipient instanceof Company ? ($recipient->legal_name ?: $recipient->name) : $recipient->display_name,
            'email' => $email,
            'address_line_1' => $recipient->address_line_1,
            'address_line_2' => $recipient->address_line_2,
            'postal_code' => $recipient->postal_code,
            'city' => $recipient->city,
            'country_code' => $recipient->country_code,
        ];
    }
}
