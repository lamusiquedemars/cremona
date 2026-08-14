<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationAuditLog;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationIntegrationManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class OrganizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_and_creation_is_audited_without_secrets(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $manager = app(OrganizationIntegrationManager::class);

        $integration = app(OrganizationContext::class)->run(
            $organization,
            fn () => $manager->create('brevo', 'transactional', [
                'api_key' => 'plain-secret-value',
            ], $actor),
        );

        $storedCredentials = DB::table('organization_integrations')
            ->where('id', $integration->id)
            ->value('credentials');

        $this->assertStringNotContainsString('plain-secret-value', $storedCredentials);
        $this->assertSame('plain-secret-value', $integration->credentials['api_key']);

        $log = app(OrganizationContext::class)->run(
            $organization,
            fn () => OrganizationAuditLog::query()->sole(),
        );

        $this->assertSame('integration.created', $log->event);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertStringNotContainsString('plain-secret-value', json_encode($log->metadata));
    }

    public function test_an_integration_can_be_revoked_and_revocation_is_audited(): void
    {
        $organization = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $manager = app(OrganizationIntegrationManager::class);

        $integration = $context->run(
            $organization,
            fn () => $manager->create('openai', 'qualification', ['api_key' => 'secret']),
        );

        $context->run($organization, fn () => $manager->revoke($integration));

        $this->assertSame('revoked', $integration->refresh()->status);
        $this->assertSame([], $integration->credentials);
        $this->assertNotNull($integration->revoked_at);
        $this->assertSame(
            ['integration.created', 'integration.revoked'],
            $context->run(
                $organization,
                fn () => OrganizationAuditLog::query()->orderBy('id')->pluck('event')->all(),
            ),
        );
    }

    public function test_sensitive_audit_metadata_is_redacted_recursively(): void
    {
        $organization = Organization::factory()->create();

        $log = app(OrganizationContext::class)->run(
            $organization,
            fn () => app(AuditLogger::class)->record('security.test', metadata: [
                'api_key' => 'top-secret',
                'nested' => ['password' => 'also-secret'],
                'provider' => 'brevo',
            ]),
        );

        $this->assertSame('[REDACTED]', $log->metadata['api_key']);
        $this->assertSame('[REDACTED]', $log->metadata['nested']['password']);
        $this->assertSame('brevo', $log->metadata['provider']);
    }

    public function test_integrations_and_audit_logs_are_isolated_between_organizations(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $manager = app(OrganizationIntegrationManager::class);

        $context->run($first, fn () => $manager->create('brevo', 'default', ['api_key' => 'first']));
        $context->run($second, fn () => $manager->create('brevo', 'default', ['api_key' => 'second']));

        $this->assertSame(1, $context->run($first, fn () => OrganizationIntegration::query()->count()));
        $this->assertSame(1, $context->run($second, fn () => OrganizationIntegration::query()->count()));
        $this->assertSame(0, OrganizationIntegration::query()->count());
        $this->assertSame(0, OrganizationAuditLog::query()->count());
    }

    public function test_an_integration_from_another_organization_cannot_be_revoked(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $manager = app(OrganizationIntegrationManager::class);

        $integration = $context->run(
            $first,
            fn () => $manager->create('openai', 'default', ['api_key' => 'secret']),
        );

        $this->expectException(LogicException::class);

        $context->run($second, fn () => $manager->revoke($integration));
    }

    public function test_audit_log_entries_cannot_be_changed(): void
    {
        $organization = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $manager = app(OrganizationIntegrationManager::class);

        $context->run(
            $organization,
            fn () => $manager->create('brevo', 'default', ['api_key' => 'secret']),
        );

        $log = $context->run($organization, fn () => OrganizationAuditLog::query()->sole());

        $this->expectException(LogicException::class);

        $log->update(['event' => 'tampered']);
    }

    public function test_audit_log_entries_cannot_be_deleted(): void
    {
        $organization = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $manager = app(OrganizationIntegrationManager::class);

        $context->run(
            $organization,
            fn () => $manager->create('brevo', 'default', ['api_key' => 'secret']),
        );

        $log = $context->run($organization, fn () => OrganizationAuditLog::query()->sole());

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
