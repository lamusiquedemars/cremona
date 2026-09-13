<?php

namespace Tests\Feature;

use App\Enums\WorkshopOrderStatus;
use App\Enums\QuoteStatus;
use App\Models\Organization;
use App\Models\StockItem;
use App\Models\WorkshopOrder;
use App\Services\WorkshopOrderQuoteManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workshop_order_is_scoped_and_records_its_return(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $order = WorkshopOrder::query()->create(['title' => 'Révision du violon', 'status' => WorkshopOrderStatus::Received]);
            $this->assertNotNull($order->received_at);
            $this->assertStringStartsWith('AT-', $order->reference);
            $order->update(['status' => WorkshopOrderStatus::Returned]);
            $this->assertNotNull($order->returned_at);
        });
    }

    public function test_selected_services_and_stock_items_create_quote_lines_once(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $order = WorkshopOrder::query()->create(['title' => 'Révision']);
            $order->services()->create(['label_snapshot' => 'Reméchage', 'quantity' => 1, 'unit_amount' => 85, 'include_in_quote' => true]);
            $stock = StockItem::query()->create(['name' => 'Jeu de cordes', 'quantity_on_hand' => 3, 'suggested_unit_amount' => 24]);
            $order->stockItems()->create(['stock_item_id' => $stock->id, 'label_snapshot' => $stock->name, 'quantity' => 1, 'unit_amount' => 24, 'include_in_quote' => true]);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            app(WorkshopOrderQuoteManager::class)->sync($order);
            $this->assertSame(2, $quote->lines()->count());
            $this->assertSame('109.00', $quote->total_amount);
        });
    }

    public function test_an_sent_workshop_quote_cannot_be_changed_automatically(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $order = WorkshopOrder::query()->create(['title' => 'Révision']);
            $order->services()->create(['label_snapshot' => 'Reméchage', 'quantity' => 1, 'unit_amount' => 85, 'include_in_quote' => true]);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            $quote->update(['status' => QuoteStatus::Sent]);

            $this->expectException(\LogicException::class);
            app(WorkshopOrderQuoteManager::class)->sync($order);
        });
    }
}
