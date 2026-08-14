<?php

namespace App\Tenancy;

use App\Models\Organization;
use Closure;
use LogicException;

class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }

    public function require(): Organization
    {
        return $this->organization ?? throw new LogicException('No active organization has been selected.');
    }

    public function forget(): void
    {
        $this->organization = null;
    }

    public function run(Organization $organization, Closure $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback($organization);
        } finally {
            $this->organization = $previous;
        }
    }
}
