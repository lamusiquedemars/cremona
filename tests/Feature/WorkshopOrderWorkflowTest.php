<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\WorkshopOrder;
use App\Services\QuoteWorkflowManager;
use App\Services\WorkshopOrderQuoteManager;
use App\Services\WorkshopOrderWorkflowManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_workflow_requires_a_diagnosis_then_an_accepted_quote_before_work_starts(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier test', 'legal_name' => 'Atelier test', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue du Test', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
            ]);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision du violon',
                'diagnosis' => 'Âme à remplacer et cordes à renouveler.',
            ]);
            $workflow = app(WorkshopOrderWorkflowManager::class);

            $workflow->confirmDiagnosis($order);
            $this->assertSame(WorkshopOrderStatus::Diagnosed, $order->fresh()->status);

            $order->services()->create([
                'label_snapshot' => 'Remplacement d’âme',
                'quantity' => 1,
                'unit_amount' => 45,
                'include_in_quote' => true,
            ]);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote->update(['person_id' => $person->id]);
            $this->assertSame($person->id, $order->fresh()->person_id);
            app(QuoteWorkflowManager::class)->markSent($quote, 'email');

            $this->assertSame(WorkshopOrderStatus::AwaitingApproval, $order->fresh()->status);

            app(QuoteWorkflowManager::class)->markAccepted($quote);
            $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
            $this->assertSame(WorkshopOrderStatus::Approved, $order->fresh()->status);
            $approvedAt = $order->fresh()->authorized_at;
            $this->assertNotNull($approvedAt);
            $this->assertNull($order->fresh()->scheduled_at);
            $this->travel(1)->days();

            $workflow->schedule($order, now()->addWeek());
            $this->assertTrue($approvedAt->equalTo($order->fresh()->authorized_at));
            $workflow->start($order);
            $workflow->markReady($order);
            $workflow->markReturned($order);

            $order->refresh();
            $this->assertSame(WorkshopOrderStatus::Returned, $order->status);
            $this->assertNotNull($order->scheduled_at);
            $this->assertNotNull($order->started_at);
            $this->assertNotNull($order->ready_at);
            $this->assertNotNull($order->returned_at);
        });
    }

    public function test_quote_updates_preserve_existing_customer_and_advanced_workshop_status(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $customer = Person::query()->create(['display_name' => 'Client atelier']);
            $recipient = Person::query()->create(['display_name' => 'Destinataire devis']);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision', 'person_id' => $customer->id,
                'status' => WorkshopOrderStatus::InProgress,
            ]);
            $quote = Quote::query()->create([
                'title' => 'Révision', 'workshop_order_id' => $order->id,
                'person_id' => $recipient->id, 'status' => QuoteStatus::Accepted,
            ]);
            $quote->update(['notes' => 'Modification après accord']);
            $this->assertSame($customer->id, $order->fresh()->person_id);
            $this->assertSame(WorkshopOrderStatus::InProgress, $order->fresh()->status);
        });
    }

    public function test_reconciliation_repairs_existing_orders_and_is_idempotent(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $customer = Person::query()->create(['display_name' => 'Client devis']);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision', 'status' => WorkshopOrderStatus::AwaitingApproval,
            ]);
            $quote = Quote::withoutEvents(fn () => Quote::query()->forceCreate([
                'organization_id' => $order->organization_id,
                'public_id' => (string) Str::ulid(),
                'reference' => 'Q-LEGACY', 'title' => 'Révision',
                'workshop_order_id' => $order->id, 'person_id' => $customer->id,
                'status' => QuoteStatus::Accepted, 'accepted_at' => now()->subDay(),
            ]));
            $migration = require database_path('migrations/2026_09_18_000000_reconcile_workshop_orders_with_quotes.php');
            $migration->up();
            $migration->up();
            $this->assertSame($customer->id, $order->fresh()->person_id);
            $this->assertSame(WorkshopOrderStatus::Approved, $order->fresh()->status);
            $this->assertTrue($quote->accepted_at->equalTo($order->fresh()->authorized_at));
        });
    }
}
