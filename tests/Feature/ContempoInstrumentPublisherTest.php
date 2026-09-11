<?php

namespace Tests\Feature;

use App\Enums\InstrumentAssetStatus;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\ContempoInstrumentPublisher;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContempoInstrumentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_instrument_is_sent_as_archived_to_remove_it_from_the_public_catalogue(): void
    {
        Http::fake(['https://contempo.test/api/instruments' => Http::response([], 201)]);

        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationIntegration::query()->create([
                'provider' => 'contempo_cms',
                'name' => 'instrument_projection',
                'credentials' => ['endpoint' => 'https://contempo.test/api/instruments', 'token' => 'test-token'],
                'status' => 'active',
            ]);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Violon d’essai',
                'reference' => 'V-001',
                'status' => InstrumentAssetStatus::Available,
                'is_site_published' => false,
            ]);

            app(ContempoInstrumentPublisher::class)->publish($instrument);
        });

        Http::assertSent(fn ($request): bool => $request['availability'] === 'archived');
    }
}
