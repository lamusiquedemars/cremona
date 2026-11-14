<?php

namespace Tests\Feature;

use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\Rental;
use App\Models\ServiceDefinition;
use App\Models\StockItem;
use App\Models\WorkshopOrder;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContempoDemoScenarioCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_scenario_is_idempotent_and_can_be_purged_without_touching_other_data(): void
    {
        $organization = Organization::factory()->create(['name' => 'Contempo Luthiers', 'slug' => 'contempo-luthiers']);

        Artisan::call('cremona:seed-contempo-demo', ['organization' => $organization->slug]);
        Artisan::call('cremona:seed-contempo-demo', ['organization' => $organization->slug]);

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertSame(1, WorkshopOrder::query()->where('reference', 'DEMO-CONTEMPO-ATELIER')->count());
            $this->assertSame(1, Rental::query()->where('reference', 'DEMO-CONTEMPO-LOCATION')->count());
            $this->assertSame(2, InstrumentAsset::query()->where('reference', 'like', 'DEMO-CONTEMPO-%')->count());
            $this->assertSame(4, StockItem::query()->where('sku', 'like', 'DEMO-CONTEMPO-%')->count());
            $this->assertSame(3, ServiceDefinition::query()->where('code', 'like', 'DEMO-CONTEMPO-%')->count());
        });

        Artisan::call('cremona:purge-contempo-demo', ['organization' => $organization->slug, '--force' => true]);

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->assertSame(0, WorkshopOrder::query()->where('reference', 'DEMO-CONTEMPO-ATELIER')->count());
            $this->assertSame(0, Rental::query()->where('reference', 'DEMO-CONTEMPO-LOCATION')->count());
            $this->assertSame(0, InstrumentAsset::query()->where('reference', 'like', 'DEMO-CONTEMPO-%')->count());
            $this->assertSame(0, StockItem::query()->where('sku', 'like', 'DEMO-CONTEMPO-%')->count());
            $this->assertSame(0, ServiceDefinition::query()->where('code', 'like', 'DEMO-CONTEMPO-%')->count());
            $this->assertSame(0, Quote::query()->count());
        });
    }
}
