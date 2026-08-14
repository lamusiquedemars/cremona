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
        return in_array($this, [self::Owner, self::Administrator], true);
    }
}
