<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkshopOrder;
use App\Services\OrganizationModuleRegistry;
use App\Services\WorkshopOrderQuoteManager;
use App\Services\WorkshopReferenceManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_and_quote_share_a_number_even_when_other_quotes_are_created_first(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 18));
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $order = WorkshopOrder::create(['title' => 'Violon']);
            $this->assertSame('AT260001', $order->reference);
            $this->assertSame('D260002', Quote::create(['title' => 'Autre devis'])->reference);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            $this->assertSame('D260001', $quote->reference);
            $this->assertSame($quote->id, app(WorkshopOrderQuoteManager::class)->sync($order)->id);
            $this->assertSame('AT260003', WorkshopOrder::create(['title' => 'Alto'])->reference);
            $this->travelTo(now()->setDate(2027, 1, 1));
            $this->assertSame('AT270001', WorkshopOrder::create(['title' => 'Nouvelle année'])->reference);
        });
    }

    public function test_a_quote_created_the_following_year_keeps_its_workshop_number(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31));
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $order = WorkshopOrder::create(['title' => 'Violon']);
            $this->travelTo(now()->setDate(2027, 1, 1));
            $this->assertSame('D260001', app(WorkshopOrderQuoteManager::class)->sync($order)->reference);
            $this->assertSame('D270001', Quote::create(['title' => 'Autre devis'])->reference);
        });
    }

    public function test_reconciliation_preserves_old_references_and_existing_modern_quote_numbers(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 18));
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $order = WorkshopOrder::create(['title' => 'Violon', 'reference' => 'AT-01M25KZJ']);
            $quote = Quote::create(['title' => 'Révision', 'reference' => 'Q-01M25M17', 'workshop_order_id' => $order->id]);
            $other = WorkshopOrder::create(['title' => 'Alto', 'reference' => 'AT-OLD']);
            $modern = Quote::create(['title' => 'Devis moderne', 'reference' => 'D260042', 'workshop_order_id' => $other->id]);
            $manager = app(WorkshopReferenceManager::class);
            $manager->reconcile();
            $manager->reconcile();
            $this->assertSame('D260043', $quote->fresh()->reference);
            $this->assertSame('AT260043', $order->fresh()->reference);
            $this->assertSame('Q-01M25M17', $quote->fresh()->previous_reference);
            $this->assertSame('AT-01M25KZJ', $order->fresh()->previous_reference);
            $this->assertSame('D260042', $modern->fresh()->reference);
            $this->assertSame('AT260042', $other->fresh()->reference);
        });
    }

    public function test_customer_changes_flow_both_ways_for_drafts_but_preserve_sent_quotes(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $first = Person::create(['display_name' => 'Premier']);
            $second = Person::create(['display_name' => 'Second']);
            $order = WorkshopOrder::create(['title' => 'Violon']);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            $quote->update(['person_id' => $first->id]);
            $this->assertSame($first->id, $order->fresh()->person_id);
            $quote->update(['person_id' => $second->id]);
            $this->assertSame($second->id, $order->fresh()->person_id);
            $order->refresh()->update(['person_id' => $first->id]);
            $this->assertSame($first->id, $quote->fresh()->person_id);
            $quote->refresh()->update(['status' => QuoteStatus::Sent]);
            $order->update(['person_id' => $second->id]);
            $this->assertSame($first->id, $quote->fresh()->person_id);
        });
    }

    public function test_linked_documents_can_be_opened_from_each_other(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['quotes', 'workshop']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);
        [$order, $quote] = app(OrganizationContext::class)->run($organization, function (): array {
            $order = WorkshopOrder::create(['title' => 'Violon']);

            return [$order, app(WorkshopOrderQuoteManager::class)->sync($order)];
        });
        $orderUrl = WorkshopOrderResource::getUrl('edit', ['record' => $order], tenant: $organization);
        $quoteUrl = QuoteResource::getUrl('view', ['record' => $quote], tenant: $organization);
        $this->actingAs($user)->get($orderUrl)->assertOk()->assertSee('Devis '.$quote->reference)->assertSee($quoteUrl, false);
        $this->get($quoteUrl)->assertOk()->assertSee('Dossier atelier '.$order->reference)->assertSee($orderUrl, false);
    }

    public function test_a_second_generated_quote_cannot_reuse_a_workshop_number(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $order = WorkshopOrder::create(['title' => 'Violon']);
            app(WorkshopOrderQuoteManager::class)->sync($order);
            $this->expectException(\LogicException::class);
            Quote::create(['title' => 'Doublon', 'workshop_order_id' => $order->id]);
        });
    }
}
