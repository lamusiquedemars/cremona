<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case ManageMembers = 'manage_members';
    case ManageModules = 'manage_modules';
    case ManageIntegrations = 'manage_integrations';
    case ViewAuditLog = 'view_audit_log';
    case ViewCrm = 'view_crm';
    case ManageCrm = 'manage_crm';
    case ViewCorrespondence = 'view_correspondence';
    case ReplyCorrespondence = 'reply_correspondence';
    case ManageCorrespondenceLinks = 'manage_correspondence_links';
    case EraseCorrespondence = 'erase_correspondence';
    case ManageEmailMailboxes = 'manage_email_mailboxes';
    case ManageOrganizationSettings = 'manage_organization_settings';
    case ManageCommercialReferences = 'manage_commercial_references';
    case ManageRentalPricing = 'manage_rental_pricing';

    public function label(): string
    {
        return match ($this) {
            self::ManageMembers => 'Gérer les membres',
            self::ManageModules => 'Gérer les modules',
            self::ManageIntegrations => 'Gérer les intégrations',
            self::ViewAuditLog => 'Consulter le journal d’audit',
            self::ViewCrm => 'Consulter la relation client',
            self::ManageCrm => 'Gérer la relation client',
            self::ViewCorrespondence => 'Consulter les correspondances',
            self::ReplyCorrespondence => 'Répondre aux correspondances',
            self::ManageCorrespondenceLinks => 'Rattacher les correspondances',
            self::EraseCorrespondence => 'Effacer les correspondances',
            self::ManageEmailMailboxes => 'Gérer les boîtes email',
            self::ManageOrganizationSettings => 'Gérer la configuration de l’organisation',
            self::ManageCommercialReferences => 'Gérer les modèles commerciaux',
            self::ManageRentalPricing => 'Gérer les tarifs de location et d’assurance',
        };
    }

    public function requiresExplicitGrant(): bool
    {
        return $this->isOrganizationConfiguration() || $this->isReferenceData();
    }

    public function isOrganizationConfiguration(): bool
    {
        return in_array($this, [
            self::ManageMembers,
            self::ManageModules,
            self::ManageIntegrations,
            self::ViewAuditLog,
            self::ManageEmailMailboxes,
            self::ManageOrganizationSettings,
        ], true);
    }

    public function isReferenceData(): bool
    {
        return in_array($this, [
            self::ManageCommercialReferences,
            self::ManageRentalPricing,
        ], true);
    }

    /** @return array<string, string> */
    public static function delegableOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $permission): bool => $permission->requiresExplicitGrant() && $permission !== self::ManageMembers && $permission !== self::ManageModules && $permission !== self::ViewAuditLog && $permission !== self::ManageIntegrations && $permission !== self::ManageEmailMailboxes)
            ->mapWithKeys(fn (self $permission): array => [$permission->value => $permission->label()])
            ->all();
    }
}
