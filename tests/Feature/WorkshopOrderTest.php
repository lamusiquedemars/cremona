<?php

namespace Tests\Feature;

use App\Enums\WorkshopOrderStatus;
use App\Models\Organization;
use App\Models\WorkshopOrder;
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
}
