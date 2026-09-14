<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\Organization;
use App\Models\WorkshopOrder;
use App\Services\QuoteWorkflowManager;
use App\Services\WorkshopOrderQuoteManager;
use App\Services\WorkshopOrderWorkflowManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_workflow_requires_a_diagnosis_then_an_accepted_quote_before_work_starts(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
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
            app(QuoteWorkflowManager::class)->markSent($quote, 'email');

            $this->assertSame(WorkshopOrderStatus::AwaitingApproval, $order->fresh()->status);

            app(QuoteWorkflowManager::class)->markAccepted($quote);
            $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);

            $workflow->schedule($order, now()->addWeek());
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
}
