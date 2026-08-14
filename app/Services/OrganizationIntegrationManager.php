<?php

namespace App\Services;

use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * @return array{integration: OrganizationIntegration, token: string}
     */
    public function createApiToken(
        string $provider,
        string $name,
        ?User $actor = null,
    ): array {
        return DB::transaction(function () use ($provider, $name, $actor): array {
            $keyId = (string) Str::ulid();
            $secret = Str::random(64);
            $integration = $this->create($provider, $name, [], $actor);
            $integration->update([
                'key_id' => $keyId,
                'token_hash' => hash('sha256', $secret),
            ]);

            return [
                'integration' => $integration,
                'token' => "{$keyId}.{$secret}",
            ];
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
