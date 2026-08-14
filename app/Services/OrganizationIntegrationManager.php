<?php

namespace App\Services;

use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class OrganizationIntegrationManager
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OrganizationContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function create(
        string $provider,
        string $name,
        array $credentials,
        ?User $actor = null,
    ): OrganizationIntegration {
        return DB::transaction(function () use ($provider, $name, $credentials, $actor): OrganizationIntegration {
            $integration = OrganizationIntegration::query()->create([
                'provider' => $provider,
                'name' => $name,
                'credentials' => $credentials,
                'status' => 'active',
            ]);

            $this->auditLogger->record(
                event: 'integration.created',
                subject: $integration,
                actor: $actor,
                metadata: ['provider' => $provider, 'name' => $name],
            );

            return $integration;
        });
    }

    public function revoke(OrganizationIntegration $integration, ?User $actor = null): void
    {
        if ($integration->organization_id !== $this->context->require()->getKey()) {
            throw new LogicException('The integration does not belong to the active organization.');
        }

        DB::transaction(function () use ($integration, $actor): void {
            $integration->update([
                'credentials' => [],
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            $this->auditLogger->record(
                event: 'integration.revoked',
                subject: $integration,
                actor: $actor,
                metadata: ['provider' => $integration->provider, 'name' => $integration->name],
            );
        });
    }
}
