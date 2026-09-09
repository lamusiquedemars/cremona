<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Organization;
use App\Models\StockItem;
use App\Models\WorkshopOrder;
use App\Services\StockManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_each_stock_movement_and_refuses_a_negative_balance(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $item = StockItem::query()->create(['name' => 'Jeu de cordes', 'quantity_on_hand' => 2]);
            $movement = app(StockManager::class)->record($item, StockMovementType::Consumption, 1, 'Montage');

            $this->assertSame('-1.00', $movement->quantity_change);
            $this->assertSame('1.00', $item->fresh()->quantity_on_hand);
            $this->expectException(LogicException::class);
            app(StockManager::class)->record($item, StockMovementType::Sale, 2);
        });
    }

    public function test_it_consumes_each_workshop_stock_line_only_once(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $item = StockItem::query()->create(['name' => 'Âme de violon', 'quantity_on_hand' => 3]);
            $order = WorkshopOrder::query()->create(['title' => 'Réglage du violon']);
            $order->stockItems()->create(['stock_item_id' => $item->id, 'label_snapshot' => $item->name, 'quantity' => 1]);

            $this->assertSame(1, app(StockManager::class)->applyWorkshopConsumption($order));
            $this->assertSame(0, app(StockManager::class)->applyWorkshopConsumption($order));
            $this->assertSame('2.00', $item->fresh()->quantity_on_hand);
            $this->assertSame(1, $item->movements()->count());
        });
    }
}
