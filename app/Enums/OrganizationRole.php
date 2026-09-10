<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Collaborator = 'collaborator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Responsable client',
            self::Collaborator => 'Équipe',
            self::Viewer => 'Lecture seule',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Pilote les demandes, le CRM et les correspondances.',
            self::Collaborator => 'Traite le CRM et répond aux correspondances.',
            self::Viewer => 'Consulte les informations sans les modifier.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $role): array => [...$options, $role->value => $role->label()],
            [],
        );
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
            self::Owner => [
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ManageCrm,
                OrganizationPermission::ViewCorrespondence,
                OrganizationPermission::ReplyCorrespondence,
                OrganizationPermission::ManageCorrespondenceLinks,
            ],
            self::Collaborator => [
                OrganizationPermission::ViewCrm,
                OrganizationPermission::ManageCrm,
                OrganizationPermission::ViewCorrespondence,
                OrganizationPermission::ReplyCorrespondence,
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
