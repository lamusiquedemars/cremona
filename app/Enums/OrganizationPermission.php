<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case ManageMembers = 'manage_members';
    case ManageModules = 'manage_modules';
    case ManageIntegrations = 'manage_integrations';
    case ViewAuditLog = 'view_audit_log';

    public function label(): string
    {
        return match ($this) {
            self::ManageMembers => 'Gérer les membres',
            self::ManageModules => 'Gérer les modules',
            self::ManageIntegrations => 'Gérer les intégrations',
            self::ViewAuditLog => 'Consulter le journal d’audit',
        };
    }
}
