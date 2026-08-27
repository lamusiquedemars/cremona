<?php

namespace App\Tenancy;

use App\Models\Organization;
use Closure;
use Filament\Facades\Filament;
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
        if ($this->organization instanceof Organization) {
            return $this->organization;
        }

        // Livewire replays persistent middleware in a short pipeline before
        // executing the component action. Filament retains the tenant it has
        // already authenticated, while this scoped context is released when
        // that pipeline returns. Use only that validated tenant as a fallback.
        $tenant = Filament::getTenant();

        return $tenant instanceof Organization ? $tenant : null;
    }

    public function id(): ?int
    {
        return $this->current()?->getKey();
    }

    public function require(): Organization
    {
        return $this->current() ?? throw new LogicException('No active organization has been selected.');
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
