<?php

namespace Tests\Feature;

use App\Console\Commands\SyncEmailMailboxes;
use App\Models\EmailMailbox;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\EmailMailboxSynchronizer;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncEmailMailboxesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retries_an_old_degraded_mailbox_but_never_a_paused_or_recently_failed_one(): void
    {
        $organization = Organization::factory()->create();
        $mailboxes = app(OrganizationContext::class)->run($organization, function (): array {
            $create = function (string $address, string $status, mixed $lastErrorAt = null): EmailMailbox {
                $integration = OrganizationIntegration::query()->create([
                    'provider' => 'email',
                    'name' => $address,
                    'credentials' => [],
                    'status' => 'active',
                ]);

                return EmailMailbox::query()->create([
                    'organization_integration_id' => $integration->id,
                    'address' => $address,
                    'status' => $status,
                    'last_error_at' => $lastErrorAt,
                ]);
            };

            return [
                $create('active@example.test', 'active'),
                $create('retry@example.test', 'degraded', now()->subHours(2)),
                $create('recent@example.test', 'degraded', now()->subMinutes(20)),
                $create('paused@example.test', 'paused'),
            ];
        });

        $synchronizer = Mockery::mock(EmailMailboxSynchronizer::class);
        $synchronizer->shouldReceive('sync')->twice()->andReturn(['imported' => 0, 'skipped' => 0]);
        $this->app->instance(EmailMailboxSynchronizer::class, $synchronizer);

        $this->artisan(SyncEmailMailboxes::class)->assertSuccessful();
    }
}
