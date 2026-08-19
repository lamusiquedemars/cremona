<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Collaborator = 'collaborator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Administrator => 'Administrateur',
            self::Collaborator => 'Collaborateur',
            self::Viewer => 'Lecture seule',
        };
    }

    public function canManageMembers(): bool
    {
        return $this->grants(OrganizationPermission::ManageMembers);
    }

    /**
     * @return array<OrganizationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => OrganizationPermission::cases(),
            self::Administrator => [
                OrganizationPermission::ManageMembers,
                OrganizationPermission::ManageModules,
                OrganizationPermission::ManageIntegrations,
                OrganizationPermission::ViewAuditLog,
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ManageCrm,
                OrganizationPermission::ViewCorrespondence,
                OrganizationPermission::ReplyCorrespondence,
                OrganizationPermission::ManageCorrespondenceLinks,
                OrganizationPermission::EraseCorrespondence,
                OrganizationPermission::ManageEmailMailboxes,
            ],
            self::Collaborator => [
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ManageCrm,
                OrganizationPermission::ViewCorrespondence,
                OrganizationPermission::ReplyCorrespondence,
                OrganizationPermission::ManageCorrespondenceLinks,
            ],
            self::Viewer => [
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ViewCorrespondence,
            ],
        };
    }

    public function grants(OrganizationPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
