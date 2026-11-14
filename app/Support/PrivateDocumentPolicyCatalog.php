<?php

namespace App\Support;

use App\Models\Organization;

class PrivateDocumentPolicyCatalog
{
    /** @return array<string, array{label: string, default_retention_months: int, default_access: string, default_requires_link: bool}> */
    public static function definitions(): array
    {
        return [
            'commercial_draft' => ['label' => 'Brouillon commercial', 'default_retention_months' => 12, 'default_access' => 'standard', 'default_requires_link' => false],
            'customer_file' => ['label' => 'Dossier client', 'default_retention_months' => 60, 'default_access' => 'standard', 'default_requires_link' => true],
            'workshop_record' => ['label' => 'Dossier atelier', 'default_retention_months' => 60, 'default_access' => 'standard', 'default_requires_link' => true],
            'rental_contract' => ['label' => 'Contrat de location', 'default_retention_months' => 60, 'default_access' => 'standard', 'default_requires_link' => true],
            'accounting_record' => ['label' => 'Pièce comptable', 'default_retention_months' => 120, 'default_access' => 'restricted', 'default_requires_link' => true],
            'identity_proof' => ['label' => 'Justificatif d’identité', 'default_retention_months' => 12, 'default_access' => 'restricted', 'default_requires_link' => true],
            'general' => ['label' => 'Autre document privé', 'default_retention_months' => 36, 'default_access' => 'standard', 'default_requires_link' => false],
        ];
    }

    /** @return array<string, array{retention_months: int, access: string, requires_link: bool}> */
    public function rules(Organization $organization): array
    {
        $configured = $organization->settings['document_policy']['categories'] ?? [];
        $rules = [];

        foreach (self::definitions() as $key => $definition) {
            $rule = is_array($configured[$key] ?? null) ? $configured[$key] : [];
            $rules[$key] = [
                'retention_months' => max(1, min(240, (int) ($rule['retention_months'] ?? $definition['default_retention_months']))),
                'access' => in_array($rule['access'] ?? null, ['standard', 'restricted'], true) ? $rule['access'] : $definition['default_access'],
                'requires_link' => (bool) ($rule['requires_link'] ?? $definition['default_requires_link']),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $definition): string => $definition['label'], self::definitions());
    }

    public function ruleFor(Organization $organization, ?string $category): array
    {
        $category = array_key_exists((string) $category, self::definitions()) ? (string) $category : 'general';

        return $this->rules($organization)[$category];
    }
}
