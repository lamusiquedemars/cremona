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
     * @param  array<string, mixed>  $credentials
     */
    public function configure(
        string $provider,
        string $name,
        array $credentials,
        ?User $actor = null,
    ): OrganizationIntegration {
        return DB::transaction(function () use ($provider, $name, $credentials, $actor): OrganizationIntegration {
            $integration = OrganizationIntegration::query()->firstOrNew([
                'provider' => $provider,
                'name' => $name,
            ]);
            $event = $integration->exists ? 'integration.updated' : 'integration.created';

            $integration->fill([
                'credentials' => $credentials,
                'status' => 'active',
                'revoked_at' => null,
            ])->save();

            $this->auditLogger->record(
                event: $event,
                subject: $integration,
                actor: $actor,
                metadata: ['provider' => $provider, 'name' => $name],
            );

            return $integration;
        });
    }

    /**
     * Replaces credentials even when the previous encrypted value was created
     * with an application key that is no longer available.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function replaceCredentials(
        OrganizationIntegration $integration,
        array $credentials,
        ?User $actor = null,
    ): OrganizationIntegration {
        if ($integration->organization_id !== $this->context->require()->getKey()) {
            throw new LogicException('The integration does not belong to the active organization.');
        }

        return DB::transaction(function () use ($integration, $credentials, $actor): OrganizationIntegration {
            // Let Laravel's encrypted cast create the new ciphertext, but use
            // the query builder to avoid reading/comparing an undecipherable
            // previous ciphertext during Eloquent's dirty check.
            $replacement = new OrganizationIntegration;
            $replacement->credentials = $credentials;

            DB::table('organization_integrations')
                ->where('id', $integration->getKey())
                ->update([
                    'credentials' => $replacement->getAttributes()['credentials'],
                    'status' => 'active',
                    'revoked_at' => null,
                    'updated_at' => now(),
                ]);

            $integration->refresh();

            $this->auditLogger->record(
                event: 'integration.updated',
                subject: $integration,
                actor: $actor,
                metadata: ['provider' => $integration->provider, 'name' => $integration->name],
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
        array $credentials = [],
    ): array {
        return DB::transaction(function () use ($provider, $name, $actor, $credentials): array {
            $keyId = (string) Str::ulid();
            $secret = Str::random(64);
            $integration = $this->create($provider, $name, $credentials, $actor);
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

    public function rotateApiToken(
        OrganizationIntegration $integration,
        ?User $actor = null,
    ): string {
        if ($integration->organization_id !== $this->context->require()->getKey()
            || $integration->status !== 'active') {
            throw new LogicException('The integration is not active in the current organization.');
        }

        return DB::transaction(function () use ($integration, $actor): string {
            $keyId = (string) Str::ulid();
            $secret = Str::random(64);
            $integration->update([
                'key_id' => $keyId,
                'token_hash' => hash('sha256', $secret),
            ]);

            $this->auditLogger->record(
                event: 'integration.token_rotated',
                subject: $integration,
                actor: $actor,
                metadata: ['provider' => $integration->provider, 'name' => $integration->name],
            );

            return "{$keyId}.{$secret}";
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
