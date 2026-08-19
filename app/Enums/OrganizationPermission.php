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
        };
    }
}
