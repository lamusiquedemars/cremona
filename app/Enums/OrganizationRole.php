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
     * Les profils définissent le niveau d'action ; la sélection de modules est
     * ensuite portée par l'appartenance à une organisation.
     */
    public function canManageOperationalData(): bool
    {
        return $this !== self::Viewer;
    }

    /**
     * @return array<OrganizationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => array_values(array_filter(
                OrganizationPermission::cases(),
                fn (OrganizationPermission $permission): bool => ! $permission->requiresExplicitGrant(),
            )),
            self::Administrator => [
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ManageCrm,
                OrganizationPermission::ViewCorrespondence,
                OrganizationPermission::ReplyCorrespondence,
                OrganizationPermission::ManageCorrespondenceLinks,
                OrganizationPermission::EraseCorrespondence,
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
